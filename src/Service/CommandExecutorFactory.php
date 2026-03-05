<?php

namespace Drupal\cmesh_push_content\Service;

use Drupal\Core\File\FileSystemInterface;

/**
 * Factory that delegates to the configured command executor.
 *
 * Reads $command_executor from cmesh.env.inc in the config directory
 * to determine which executor implementation to use. Defaults to systemd.
 */
class CommandExecutorFactory implements CommandExecutorInterface {

  /**
   * The resolved executor.
   *
   * @var \Drupal\cmesh_push_content\Service\CommandExecutorInterface
   */
  protected $executor;

  /**
   * Constructs a CommandExecutorFactory.
   *
   * @param \Drupal\cmesh_push_content\Service\CommandExecutorInterface $systemd_executor
   *   The systemd command executor.
   * @param \Drupal\cmesh_push_content\Service\CommandExecutorInterface $direct_executor
   *   The direct (exec) command executor.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(
    CommandExecutorInterface $systemd_executor,
    CommandExecutorInterface $direct_executor,
    FileSystemInterface $file_system,
  ) {
    $this->executor = $this->resolve($systemd_executor, $direct_executor, $file_system);
  }

  /**
   * Resolve which executor to use based on cmesh.env.inc config.
   */
  private function resolve(
    CommandExecutorInterface $systemd_executor,
    CommandExecutorInterface $direct_executor,
    FileSystemInterface $file_system,
  ): CommandExecutorInterface {
    $files_path = $file_system->realpath('public://');
    $inc = $files_path . '/cmesh-config/cmesh.env.inc';

    $command_executor = 'systemd';

    if (is_file($inc)) {
      ob_start();
      include $inc;
      ob_end_clean();
    }

    return $command_executor === 'direct' ? $direct_executor : $systemd_executor;
  }

  /**
   * {@inheritdoc}
   */
  public function executeCommand($command) {
    return $this->executor->executeCommand($command);
  }

  /**
   * {@inheritdoc}
   */
  public function executeCommandDirect($org, $name, $command_key = 'default', $bucket = '') {
    return $this->executor->executeCommandDirect($org, $name, $command_key, $bucket);
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return $this->executor->getStatus();
  }

  /**
   * {@inheritdoc}
   */
  public function stopCommand() {
    return $this->executor->stopCommand();
  }

  /**
   * {@inheritdoc}
   */
  public function clearState() {
    return $this->executor->clearState();
  }

}
