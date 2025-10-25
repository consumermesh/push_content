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
class SystemdCommandExecutorService {

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
   * Execute a command via systemd.
   *
   * @param string $command
   *   The command to execute.
   *
   * @return array
   *   Array containing process info.
   */
  public function executeCommand($command) {
    // Parse command to extract org and name
    if (!preg_match('/-o\s+\'?([^\'\\s]+)\'?\s+-n\s+\'?([^\'\\s]+)\'?/', $command, $matches)) {
      throw new \Exception('Could not parse command for org and name');
    }

    $org = $matches[1];
    $name = $matches[2];
    
    // Use colon as delimiter (better than dash for names with dashes)
    $instance = "{$org}:{$name}";
    $service_name = "cmesh-build@{$instance}";

    // Escape the instance name for systemctl (colon needs escaping in systemd)
    $escaped_instance = str_replace(':', '\\:', $instance);
    $escaped_service = "cmesh-build@{$escaped_instance}";

    // Start the systemd service
    $start_command = 'systemctl start ' . escapeshellarg($escaped_service) . ' 2>&1';
    exec($start_command, $output, $return);

    if ($return !== 0) {
      throw new \Exception('Failed to start systemd service: ' . implode("\n", $output));
    }

    // Give systemd a moment to start the service
    usleep(500000); // 500ms

    // Get the log file path
    $log_file = "/var/log/cmesh/build-{$instance}.log";

    $process_id = uniqid('systemd_', TRUE);

    // Store process information in state
    $this->state->set('cmesh_push_content.current', [
      'process_id' => $process_id,
      'command' => $command,
      'service_name' => $escaped_service,
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
