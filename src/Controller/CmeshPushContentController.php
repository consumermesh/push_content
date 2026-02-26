<?php

namespace Drupal\cmesh_push_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\cmesh_push_content\Service\CommandExecutorInterface;

/**
 * Controller for command executor AJAX endpoints.
 */
class CmeshPushContentController extends ControllerBase {

  /**
   * The command executor service.
   *
   * @var \Drupal\cmesh_push_content\Service\CommandExecutorInterface
   */
  protected $commandExecutor;

  /**
   * Constructs a CmeshPushContentController object.
   *
   * @param \Drupal\cmesh_push_content\Service\CommandExecutorInterface $command_executor
   *   The command executor service.
   */
  public function __construct(CommandExecutorInterface $command_executor) {
    $this->commandExecutor = $command_executor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cmesh_push_content.service')
    );
  }

  /**
   * Execute a command.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with execution result.
   */
  public function execute(Request $request) {
    $command = $request->request->get('command');

    if (empty($command)) {
      return new JsonResponse([
        'error' => 'No command provided',
      ], 400);
    }

    $result = $this->commandExecutor->executeCommand($command);

    return new JsonResponse([
      'success' => TRUE,
      'process_id' => $result['process_id'],
      'pid' => $result['pid'],
    ]);
  }

  /**
   * Get command status.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with status information.
   */
  public function status() {
    $status = $this->commandExecutor->getStatus();

    if (!$status) {
      return new JsonResponse([
        'is_running' => FALSE,
        'output' => '',
      ]);
    }

    return new JsonResponse($status);
  }

  /**
   * API endpoint to trigger a push command.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object with JSON body containing optional command_key and env.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with execution result.
   */
  public function trigger(Request $request) {
    $content = json_decode($request->getContent(), TRUE) ?? [];
    $command_key = $content['command_key'] ?? 'default';
    $env = $content['env'] ?? NULL;

    $config = $this->loadEnvironmentConfig($env);
    if (!$config) {
      return new JsonResponse([
        'error' => 'No environment configuration found',
      ], 404);
    }

    try {
      $result = $this->commandExecutor->executeCommandDirect(
        $config['org'],
        $config['name'],
        $command_key,
        $config['bucket']
      );
      return new JsonResponse([
        'success' => TRUE,
        'process_id' => $result['process_id'],
        'pid' => $result['pid'],
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * API endpoint to get command status.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with status information.
   */
  public function apiStatus() {
    return $this->status();
  }

  /**
   * API endpoint to list available environments and their commands.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON array of environments with their commands.
   */
  public function environments() {
    $config_dir = $this->getConfigDirectory();
    if (!$config_dir) {
      return new JsonResponse([]);
    }

    $files = glob("$config_dir/*.env.inc");
    $result = [];
    foreach ($files as $file) {
      $envKey = basename($file, '.env.inc');
      $commands = $this->getEnvironmentCommands($envKey);
      $result[] = [
        'env' => $envKey,
        'commands' => array_values($commands),
      ];
    }

    return new JsonResponse($result);
  }

  /**
   * Get available commands for an environment.
   *
   * @param string $envKey
   *   The environment key.
   *
   * @return array
   *   Array of command configurations with command_key, label, description.
   */
  private function getEnvironmentCommands(string $envKey): array {
    $config_dir = $this->getConfigDirectory();
    $inc = "$config_dir/{$envKey}.env.inc";

    $org = '';
    $name = '';
    $bucket = '';
    $custom_commands = [];

    if (is_file($inc)) {
      ob_start();
      include $inc;
      ob_end_clean();
    }

    if (!empty($custom_commands) && is_array($custom_commands)) {
      $cleaned = [];
      foreach ($custom_commands as $key => $config) {
        $cleaned[$key] = [
          'command_key' => $key,
          'label' => $config['label'] ?? 'Push to ' . $key,
          'description' => $config['description'] ?? 'Deploy to ' . $key,
        ];
      }
      return $cleaned;
    }

    return [
      'default' => [
        'command_key' => 'default',
        'label' => 'Push to ' . $envKey,
        'description' => 'Push to ' . $envKey,
      ],
    ];
  }

  /**
   * Get the persistent configuration directory path.
   *
   * @return string|null
   *   The absolute path to the configuration directory, or NULL if not found.
   */
  private function getConfigDirectory(): ?string {
    $files_path = \Drupal::service('file_system')->realpath('public://');
    $config_dir = $files_path . '/cmesh-config';
    return is_dir($config_dir) ? $config_dir : NULL;
  }

  /**
   * Load environment configuration from .env.inc file.
   *
   * @param string|null $env
   *   Optional environment key. If NULL, uses the first available .env.inc file.
   *
   * @return array|null
   *   Array with 'org', 'name', 'bucket' keys, or NULL if not found.
   */
  private function loadEnvironmentConfig(?string $env = NULL): ?array {
    $config_dir = $this->getConfigDirectory();
    if (!$config_dir) {
      return NULL;
    }

    if ($env) {
      $inc = "$config_dir/{$env}.env.inc";
    }
    else {
      $files = glob("$config_dir/*.env.inc");
      $inc = $files[0] ?? NULL;
    }

    if (!$inc || !is_file($inc)) {
      return NULL;
    }

    $org = '';
    $name = '';
    $bucket = '';

    ob_start();
    include $inc;
    ob_end_clean();

    if (!$org || !$name) {
      return NULL;
    }

    return ['org' => $org, 'name' => $name, 'bucket' => $bucket];
  }

}
