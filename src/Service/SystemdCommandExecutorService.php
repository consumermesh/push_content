<?php

namespace Drupal\cmesh_push_content\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\State\StateInterface;

/**
 * Service for executing commands via systemd (for PHP-FPM compatibility).
 *
 * This service uses systemd to execute commands, completely decoupling
 * them from the PHP-FPM process. This solves issues where PHP-FPM's
 * process isolation prevents background command execution.
 */
class SystemdCommandExecutorService implements CommandExecutorInterface {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a SystemdCommandExecutorService object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(FileSystemInterface $file_system, StateInterface $state) {
    $this->fileSystem = $file_system;
    $this->state = $state;
  }

  /**
   * Execute a command via systemd (legacy method - delegates to direct method).
   *
   * @param string $command
   *   The command to execute (parsed to extract parameters).
   *
   * @return array
   *   Array containing process info.
   */
  public function executeCommand($command) {
    // Log the incoming command for debugging
    \Drupal::logger('cmesh_push_content')->info('SystemdCommandExecutor: Legacy executeCommand called with: @cmd', ['@cmd' => $command]);

    // Parse command to extract org, name, and command key for backward compatibility
    if (!preg_match('/-o\s+([\'"]?)([^\1]*)\1\s+-n\s+([\'"]?)([^\3]*)\3/', $command, $matches)) {
      $error = 'Could not parse command for org and name. Command format should be: script -o ORG -n NAME. Received: ' . $command;
      \Drupal::logger('cmesh_push_content')->error($error);
      throw new \Exception($error);
    }

    $org = $matches[2];
    $name = $matches[4];

    // Extract command key from script path
    $command_key = 'default';
    if (preg_match('/deploy-([^\.]+)\.sh/', $command, $cmd_match)) {
      $command_key = $cmd_match[1];
    } elseif (preg_match('/pushfin.*\.sh/', $command)) {
      $command_key = 'default';
    }

    // Try to extract bucket parameter for backward compatibility
    $bucket = '';
    if (preg_match('/-b\s+([\'"]?)([^\1]*)\1/', $command, $bmatch)) {
      $bucket = $bmatch[2];
    }

    // Delegate to the direct method
    return $this->executeCommandDirect($org, $name, $command_key, $bucket);
  }

  /**
   * Execute a command via systemd using direct parameters.
   *
   * @param string $org
   *   The organization name.
   * @param string $name
   *   The site name.
   * @param string $command_key
   *   The command key (e.g., 'default', 'cloudflare', 'bunny', 'aws').
   * @param string $bucket
   *   The bucket name (optional, for AWS S3 deployments).
   *
   * @return array
   *   Array containing process info.
   */
  public function executeCommandDirect($org, $name, $command_key = 'default', $bucket = '') {
    // Log the incoming parameters for debugging
    \Drupal::logger('cmesh_push_content')->info('SystemdCommandExecutor: Direct execution with org=@org, name=@name, command_key=@command_key, bucket=@bucket', [
      '@org' => $org, 
      '@name' => $name, 
      '@command_key' => $command_key,
      '@bucket' => $bucket ?: '(none)'
    ]);

    // Build instance with encoded format to handle colons in org/name
    // URL encode colons in org and name to prevent parsing issues
    $encoded_org = str_replace(':', '%3A', $org);
    $encoded_name = str_replace(':', '%3A', $name);
    
    // Include bucket in instance name if provided (for AWS deployments)
    if (!empty($bucket)) {
      $encoded_bucket = str_replace(':', '%3A', $bucket);
      $instance = "{$encoded_org}:{$encoded_name}:{$command_key}:{$encoded_bucket}";
    } else {
      $instance = "{$encoded_org}:{$encoded_name}:{$command_key}";
    }
    
    $service_name = "cmesh-build@{$instance}";

    \Drupal::logger('cmesh_push_content')->info('SystemdCommandExecutor: Starting service: @service', [
      '@service' => $service_name,
    ]);

    // Start the systemd service
    // Note: systemctl handles special characters in instance names
    $start_command = 'systemctl start ' . escapeshellarg($service_name) . ' 2>&1';
    exec($start_command, $output, $return);

    if ($return !== 0) {
      $error = 'Failed to start systemd service: ' . implode("\n", $output);
      \Drupal::logger('cmesh_push_content')->error($error);
      throw new \Exception($error);
    }

    \Drupal::logger('cmesh_push_content')->info('SystemdCommandExecutor: Service started successfully');
    // Get the log file path
    $log_file = "/var/log/cmesh/build-{$instance}.log";

    $process_id = uniqid('systemd_', TRUE);

    // Store process information in state
    $this->state->set('cmesh_push_content.current', [
      'process_id' => $process_id,
      'command' => $command,
      'service_name' => $service_name,
      'instance' => $instance,
      'log_file' => $log_file,
      'started' => time(),
      'method' => 'systemd',
    ]);

    return [
      'process_id' => $process_id,
      'pid' => $instance, // Use instance as "PID" for display
    ];
  }

  /**
   * Get the status of the current command.
   *
   * @return array|null
   *   Status information or NULL if no command is running.
   */
  public function getStatus() {
    $process_info = $this->state->get('cmesh_push_content.current');

    if (!$process_info) {
      return NULL;
    }

    // Check if service is still active
    $is_running = FALSE;
    if (!empty($process_info['service_name'])) {
      $check_command = 'systemctl is-active ' . escapeshellarg($process_info['service_name']) . ' 2>&1';
      exec($check_command, $output, $return);
      $status = trim($output[0] ?? '');
      $is_running = ($status === 'active' || $status === 'activating');
    }

    // Read output from log file
    $output = '';
    if (!empty($process_info['log_file']) && file_exists($process_info['log_file'])) {
      $output = file_get_contents($process_info['log_file']);
    }

    // If service stopped and not marked completed, mark it now
    if (!$is_running && !isset($process_info['completed'])) {
      $process_info['completed'] = time();
      $process_info['final_output'] = $output;
      $this->state->set('cmesh_push_content.current', $process_info);
    }

    // Use stored final output if completed
    if (isset($process_info['completed'])) {
      $output = $process_info['final_output'] ?? $output;
    }

    return [
      'is_running' => $is_running,
      'output' => $output,
      'command' => $process_info['command'],
      'started' => $process_info['started'],
      'completed' => $process_info['completed'] ?? NULL,
      'pid' => $process_info['instance'],
    ];
  }

  /**
   * Stop the current command execution.
   */
  public function stopCommand() {
    $process_info = $this->state->get('cmesh_push_content.current');

    if ($process_info && !empty($process_info['service_name'])) {
      // Stop the systemd service
      $stop_command = 'systemctl stop ' . escapeshellarg($process_info['service_name']) . ' 2>&1';
      exec($stop_command);

      // Clean up state
      $this->clearState();
    }
  }

  /**
   * Clear the stored state and output.
   */
  public function clearState() {
    $process_info = $this->state->get('cmesh_push_content.current');

    if ($process_info) {
      // Optionally clean up log file
      // if (!empty($process_info['log_file']) && file_exists($process_info['log_file'])) {
      //   @unlink($process_info['log_file']);
      // }

      // Clear state
      $this->state->delete('cmesh_push_content.current');
    }
  }

}
