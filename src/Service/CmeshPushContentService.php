<?php

namespace Drupal\cmesh_push_content\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\State\StateInterface;

/**
 * Service for executing commands and tracking their state.
 */
class CmeshPushContentService {

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
   * Constructs a CmeshPushContentService object.
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
   * Execute a command in the background.
   *
   * @param string $command
   *   The command to execute.
   *
   * @return array
   *   Array containing process info.
   */
  public function executeCommand($command) {
    // Increase PHP limits for long-running commands
    @ini_set('memory_limit', '2048M');
    @ini_set('max_execution_time', '900');
    @set_time_limit(900);
    
    $process_id = uniqid('cmd_', TRUE);
    $temp_dir = $this->fileSystem->getTempDirectory();
    $output_file = $temp_dir . '/' . $process_id . '_output.log';
    $pid_file = $temp_dir . '/' . $process_id . '_pid.txt';
    $script_file = $temp_dir . '/' . $process_id . '_script.sh';

    // Write command to a bash script file to avoid escaping issues
    // Add explicit output flushing for better real-time display
    $script_content = "#!/bin/bash\n";
    $script_content .= "set -o pipefail\n"; // Fail on pipe errors
    $script_content .= "exec 2>&1\n"; // Redirect stderr to stdout
    $script_content .= $command . "\n";
    $script_content .= "exit_code=$?\n";
    $script_content .= "echo \"\"\n";
    $script_content .= "echo \"[Command completed with exit code: \$exit_code]\"\n";
    $script_content .= "exit \$exit_code\n";
    
    file_put_contents($script_file, $script_content);
    chmod($script_file, 0755);
    
    // Check if stdbuf is available for unbuffered output
    $has_stdbuf = false;
    exec('which stdbuf 2>/dev/null', $stdbuf_check, $stdbuf_return);
    if ($stdbuf_return === 0 && !empty($stdbuf_check)) {
      $has_stdbuf = true;
    }
    
    // Execute script in background, redirect output to file.
    if ($has_stdbuf) {
      // Use stdbuf to disable buffering for real-time output
      $full_command = sprintf(
        'stdbuf -oL -eL bash %s > %s 2>&1 & echo $! > %s',
        escapeshellarg($script_file),
        escapeshellarg($output_file),
        escapeshellarg($pid_file)
      );
    } else {
      // Fallback without stdbuf
      $full_command = sprintf(
        'bash %s > %s 2>&1 & echo $! > %s',
        escapeshellarg($script_file),
        escapeshellarg($output_file),
        escapeshellarg($pid_file)
      );
    }

    exec($full_command);

    // Wait a moment for the PID file and initial output to be written.
    usleep(300000); // 300ms

    $pid = NULL;
    if (file_exists($pid_file)) {
      $pid = trim(file_get_contents($pid_file));
    }

    // Store process information in state.
    $this->state->set('cmesh_push_content.current', [
      'process_id' => $process_id,
      'command' => $command,
      'pid' => $pid,
      'output_file' => $output_file,
      'pid_file' => $pid_file,
      'script_file' => $script_file,
      'started' => time(),
    ]);

    return [
      'process_id' => $process_id,
      'pid' => $pid,
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

    $is_running = FALSE;
    if (!empty($process_info['pid'])) {
      // Check if process is still running.
      $is_running = $this->isProcessRunning($process_info['pid']);
    }

    $output = '';
    if (file_exists($process_info['output_file'])) {
      $output = file_get_contents($process_info['output_file']);
    }

    // If process just completed, store final output and mark as completed
    if (!$is_running && !isset($process_info['completed'])) {
      $process_info['completed'] = time();
      $process_info['final_output'] = $output;
      $this->state->set('cmesh_push_content.current', $process_info);
      
      // Clean up temporary files but keep the state with output
      $this->cleanupFiles($process_info);
    }

    // If completed, use stored final output
    if (isset($process_info['completed'])) {
      $output = $process_info['final_output'] ?? $output;
    }

    return [
      'is_running' => $is_running,
      'output' => $output,
      'command' => $process_info['command'],
      'started' => $process_info['started'],
      'completed' => $process_info['completed'] ?? NULL,
      'pid' => $process_info['pid'],
    ];
  }

  /**
   * Check if a process is running.
   *
   * @param int $pid
   *   The process ID.
   *
   * @return bool
   *   TRUE if running, FALSE otherwise.
   */
  protected function isProcessRunning($pid) {
    if (empty($pid)) {
      return FALSE;
    }

    // Use ps to check if process exists.
    $output = [];
    exec("ps -p " . escapeshellarg($pid), $output);
    
    // If more than 1 line returned, process exists (header + process line).
    return count($output) > 1;
  }

  /**
   * Clean up process files and state.
   *
   * @param array $process_info
   *   Process information array.
   */
  protected function cleanup(array $process_info) {
    $this->cleanupFiles($process_info);
    
    // Clear state.
    $this->state->delete('cmesh_push_content.current');
  }

  /**
   * Clean up temporary files only (keeps state).
   *
   * @param array $process_info
   *   Process information array.
   */
  protected function cleanupFiles(array $process_info) {
    // Delete temporary files.
    if (!empty($process_info['output_file']) && file_exists($process_info['output_file'])) {
      @unlink($process_info['output_file']);
    }
    if (!empty($process_info['pid_file']) && file_exists($process_info['pid_file'])) {
      @unlink($process_info['pid_file']);
    }
    if (!empty($process_info['script_file']) && file_exists($process_info['script_file'])) {
      @unlink($process_info['script_file']);
    }
  }

  /**
   * Stop the current running command.
   */
  public function stopCommand() {
    $process_info = $this->state->get('cmesh_push_content.current');
    
    if ($process_info && !empty($process_info['pid'])) {
      // Kill the process.
      exec('kill ' . escapeshellarg($process_info['pid']));
      
      // Clean up.
      $this->cleanup($process_info);
    }
  }

  /**
   * Clear the stored state and output.
   */
  public function clearState() {
    $process_info = $this->state->get('cmesh_push_content.current');
    
    if ($process_info) {
      $this->cleanup($process_info);
    }
  }

}
