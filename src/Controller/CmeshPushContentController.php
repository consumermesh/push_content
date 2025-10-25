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

}
