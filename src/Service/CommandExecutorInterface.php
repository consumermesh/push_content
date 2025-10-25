<?php

namespace Drupal\cmesh_push_content\Service;

/**
 * Interface for command executor services.
 */
interface CommandExecutorInterface {

  /**
   * Execute a command in the background.
   *
   * @param string $command
   *   The command to execute.
   *
   * @return array
   *   Array containing process info with keys:
   *   - process_id: Unique identifier for this process
   *   - pid: Process ID or instance identifier
   */
  public function executeCommand($command);

  /**
   * Get the status of the current command.
   *
   * @return array|null
   *   Status information or NULL if no command is running.
   *   Array contains:
   *   - is_running: Boolean indicating if command is still running
   *   - output: Current output from the command
   *   - command: The command that was executed
   *   - started: Timestamp when command started
   *   - completed: Timestamp when command completed (if finished)
   *   - pid: Process ID or instance identifier
   */
  public function getStatus();

  /**
   * Stop the current command execution.
   */
  public function stopCommand();

  /**
   * Clear the stored state and output.
   */
  public function clearState();

}
