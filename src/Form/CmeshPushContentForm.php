<?php

namespace Drupal\cmesh_push_content\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\cmesh_push_content\Service\CmeshPushContentService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for executing commands.
 */
class CmeshPushContentForm extends FormBase {

  /**
   * The command executor service.
   *
   * @var \Drupal\cmesh_push_content\Service\CmeshPushContentService
   */
  protected $commandExecutor;

  /**
   * Constructs a CmeshPushContentForm object.
   *
   * @param \Drupal\cmesh_push_content\Service\CmeshPushContentService $command_executor
   *   The command executor service.
   */
  public function __construct(CmeshPushContentService $command_executor) {
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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cmesh_push_content_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Check current status.
    $status = $this->commandExecutor->getStatus();

    $form['#attached']['library'][] = 'cmesh_push_content/cmesh_push_content';
    $form['#attached']['drupalSettings']['cmeshPushContent']['statusUrl'] = Url::fromRoute('cmesh_push_content.status')->toString();
    $form['#attached']['drupalSettings']['cmeshPushContent']['executeUrl'] = Url::fromRoute('cmesh_push_content.execute')->toString();
    $form['#attached']['drupalSettings']['cmeshPushContent']['isRunning'] = ($status && $status['is_running']) ? TRUE : FALSE;
    $form['#attached']['drupalSettings']['cmeshPushContent']['isCompleted'] = ($status && isset($status['completed'])) ? TRUE : FALSE;

    // Add wrapper for AJAX
    $form['#prefix'] = '<div id="cmesh-push-content-form">';
    $form['#suffix'] = '</div>';

    // Add hidden button to trigger form refresh when command completes
    $form['refresh_trigger'] = [
      '#type' => 'submit',
      '#value' => 'Refresh',
      '#submit' => ['::refreshForm'],
      '#ajax' => [
        'callback' => '::ajaxRebuildForm',
        'wrapper' => 'cmesh-push-content-form',
      ],
      '#attributes' => [
        'style' => 'display: none;',
        'id' => 'refresh-trigger',
      ],
    ];

    // Store which command to execute in form state
    $form['selected_command'] = [
      '#type' => 'hidden',
      '#value' => '',
    ];

    if (!$status || !$status['is_running']) {
      $form['commands_info'] = [
        '#markup' => '<p>' . $this->t('Select a command to execute:') . '</p>',
      ];
    }

    $form['status_container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'status-container'],
    ];

    if ($status) {
      if ($status['is_running']) {
        $form['status_container']['status'] = [
          '#markup' => '<div class="messages messages--status">' .
                       $this->t('Command is currently running: @cmd', ['@cmd' => $status['command']]) .
                       '<br>PID: ' . $status['pid'] .
                       '<br>Started: ' . date('Y-m-d H:i:s', $status['started']) .
                       '</div>',
        ];
      }
      elseif (isset($status['completed'])) {
        $duration = $status['completed'] - $status['started'];
        $form['status_container']['status'] = [
          '#markup' => '<div class="messages messages--status">' .
                       $this->t('Command completed successfully!') .
                       '<br>Command: ' . $status['command'] .
                       '<br>Started: ' . date('Y-m-d H:i:s', $status['started']) .
                       '<br>Completed: ' . date('Y-m-d H:i:s', $status['completed']) .
                       '<br>Duration: ' . $duration . ' seconds' .
                       '</div>',
        ];
      }
    }

    $form['output_container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'output-container'],
    ];

    $form['output_container']['output'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Output'),
      '#rows' => 15,
      '#attributes' => [
        'readonly' => 'readonly',
        'id' => 'command-output',
      ],
      '#value' => $status ? $status['output'] : '',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    if ($status && $status['is_running']) {
      unset($form['actions']);
      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['stop'] = [
        '#type' => 'submit',
        '#value' => $this->t('Stop Command'),
        '#submit' => ['::stopCommand'],
        '#ajax' => [
          'callback' => '::ajaxRebuildForm',
          'wrapper' => 'cmesh-push-content-form',
          'effect' => 'fade',
        ],
        '#attributes' => ['class' => ['button--danger']],
      ];
    }
    elseif ($status && isset($status['completed'])) {
      // Command completed - show Clear Output button
      $form['actions']['clear'] = [
        '#type' => 'submit',
        '#value' => $this->t('Clear Output'),
        '#submit' => ['::clearOutput'],
        '#ajax' => [
          'callback' => '::ajaxRebuildForm',
          'wrapper' => 'cmesh-push-content-form',
          'effect' => 'fade',
        ],
        '#attributes' => ['class' => ['button']],
      ];
    }
    else {
      foreach ($this->listEnvironments() as $envKey) {
        $form['actions']["run_$envKey"] = [
          '#type' => 'submit',
          '#value' => $this->t('Push to @env', ['@env' => $envKey]),
          '#submit' => ['::executeEnvCommand'],
          '#ajax' => [
            'callback' => '::ajaxRebuildForm',
            'wrapper' => 'cmesh-push-content-form',
            'effect' => 'fade',
          ],
          '#attributes' => ['class' => ['button', 'button--primary']],
          '#env_key' => $envKey,
        ];
      }
    }

    return $form;
  }

  /**
   * Return every *.env.inc file (without extension) from config directory.
   *
   * @return string[]
   *   Array of environment names, e.g. ['dev','staging','prod'].
   */
  private function listEnvironments(): array {
    $dir = dirname(__DIR__, 2) . '/config';
    $list = glob("$dir/*.env.inc");
    return array_map(
      fn($f) => basename($f, '.env.inc'),
      $list
    );
  }

  /**
   * Submit handler for environment commands.
   */
  public function executeEnvCommand(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $envKey = $trigger['#env_key'];

    $inc = dirname(__DIR__, 2) . "/config/{$envKey}.env.inc";
    $org = $name = NULL;
    if (is_file($inc)) {
        ob_start();        // Capture output
        include $inc;      // Include file
        ob_end_clean();    // Discard captured output
    }

    $command = sprintf(
      '/opt/cmesh/scripts/pushfin.sh -o %s -n %s',
      escapeshellarg($org),
      escapeshellarg($name)
    );

    $this->executeCommand($command, "Push to $envKey");
    $form_state->setRebuild(TRUE);
  }

  /**
   * Helper method to execute a command.
   *
   * @param string $command
   *   The command to execute.
   * @param string $description
   *   Description of the command for the status message.
   */
  protected function executeCommand($command, $description) {
    $result = $this->commandExecutor->executeCommand($command);

    $this->messenger()->addStatus(
      $this->t('@description started with PID: @pid', [
        '@description' => $description,
        '@pid' => $result['pid'],
      ])
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This method is required by FormInterface but not used
    // since we have custom submit handlers
  }

  /**
   * Stop command submit handler.
   */
  public function stopCommand(array &$form, FormStateInterface $form_state) {
    $this->commandExecutor->stopCommand();
    $this->messenger()->addStatus($this->t('Command stopped.'));
    $form_state->setRebuild(TRUE);
  }

  /**
   * Clear output submit handler.
   */
  public function clearOutput(array &$form, FormStateInterface $form_state) {
    $this->commandExecutor->clearState();
    $this->messenger()->addStatus($this->t('Output cleared.'));
    $form_state->setRebuild(TRUE);
  }

  /**
   * Refresh form submit handler (triggered by JS when status changes).
   */
  public function refreshForm(array &$form, FormStateInterface $form_state) {
    // Just rebuild the form to show updated status
    $form_state->setRebuild(TRUE);
  }

  /**
   * AJAX callback to rebuild the form.
   */
  public function ajaxRebuildForm(array &$form, FormStateInterface $form_state) {
    return $form;
  }

}
