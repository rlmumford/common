<?php

namespace Drupal\checklist_communication\Plugin\ChecklistItemHandler;

use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\checklist\Attempt\ChecklistAttemptJournal;
use Drupal\checklist\ChecklistActionResource;
use Drupal\checklist\ChecklistActionState;
use Drupal\checklist\Execution\ChecklistItemExecutor;
use Drupal\checklist\Execution\ChecklistItemResult;
use Drupal\checklist\Plugin\ChecklistItemHandler\ActionOperationsChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ActionResourceChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ActionStateChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\AutomaticChecklistItemHandlerBase;
use Drupal\checklist\Plugin\ChecklistItemHandler\ExpectedOutcomeChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\StatefulChecklistItemHandlerInterface;
use Drupal\checklist_communication\CommunicationResource;
use Drupal\communication\Entity\CommunicationInterface;
use Drupal\communication\Plugin\Communication\Operation\DeferredSaveOperationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Runs a communication operation after optional audited confirmation.
 *
 * @ChecklistItemHandler(
 *   id = "communication_operation",
 *   label = @Translation("Communication operation"),
 *   category = @Translation("Communication"),
 *   context_definitions = {
 *     "communication" = @ContextDefinition("entity:communication", required = TRUE, label = @Translation("Communication"))
 *   },
 *   forms = {
 *     "action" = "\Drupal\checklist_communication\PluginForm\OperationConfirmationForm"
 *   }
 * )
 */
class CommunicationOperation extends AutomaticChecklistItemHandlerBase implements StatefulChecklistItemHandlerInterface, ExpectedOutcomeChecklistItemHandlerInterface, ActionStateChecklistItemHandlerInterface, ActionOperationsChecklistItemHandlerInterface, ActionResourceChecklistItemHandlerInterface {

  use OperationDependenciesTrait {
    create as protected createWithOperations;
  }

  /**
   * The shared saved-communication resource builder.
   */
  protected CommunicationResource $resource;

  /**
   * The fenced item executor.
   */
  protected ChecklistItemExecutor $executor;
  /**
   * The current attempt journal.
   */
  protected ChecklistAttemptJournal $journal;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = static::createWithOperations($container, $configuration, $plugin_id, $plugin_definition);
    $instance->resource = $container->get('checklist_communication.resource');
    $instance->executor = $container->get('checklist.item_executor');
    $instance->journal = $container->get('checklist.attempt_journal');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getActionResource(): ?ChecklistActionResource {
    $communication = $this->getContext('communication')->hasContextValue() ? $this->getContextValue('communication') : NULL;
    return $this->resource->build($communication);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies() + ['module' => []];
    $settings = $this->getConfiguration();
    $dependencies['module'] = array_values(array_unique(array_merge($dependencies['module'], $this->operationModules($settings['operation'], $settings['variant']))));
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['operation' => 'send', 'variant' => '', 'confirm' => FALSE] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function stateDefinitions(): array {
    return [
      'awaiting_confirmation' => DataDefinition::create('boolean'),
      'confirmed' => DataDefinition::create('boolean'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function expectedOutcomeDefinitions(): array {
    return [
      'communication' => EntityDataDefinition::create('communication'),
      'succeeded' => DataDefinition::create('boolean'),
    ];
  }

  /**
   * Rechecks domain access and configured inputs before any external effect.
   */
  protected function operation(): array {
    $communication = $this->getContextValue('communication');
    $settings = $this->getConfiguration();
    $options = $settings['variant'] === '' ? [] : ['variant' => $settings['variant']];
    if (!$communication instanceof CommunicationInterface || $communication->isNew()) {
      throw new \DomainException('The operation requires a saved communication.');
    }
    if (!$communication->access('view') || !$communication->access('update') || !$communication->operationAccess($settings['operation'], $options)) {
      throw new AccessDeniedHttpException('The communication operation is not accessible.');
    }
    $operation = $this->operations->createInstance($settings['operation']);
    if (!$operation instanceof DeferredSaveOperationInterface) {
      throw new \DomainException('The operation must support deferred local persistence.');
    }
    $reasons = [];
    if (!$operation->applicable($communication, $options) || !$operation->validate($communication, $options, $reasons)) {
      throw new \DomainException('The configured operation is not currently valid.');
    }
    if ($operation->hasForm($communication, $options)) {
      throw new \DomainException('Configure the operation inputs before using this checklist handler.');
    }
    return [$operation, $communication, $options];
  }

  /**
   * {@inheritdoc}
   */
  public function actionIteration(ChecklistAttempt $attempt): ChecklistItemResult {
    [$operation, $communication, $options] = $this->operation();
    if ($this->getConfiguration()['confirm'] && !$this->item->get('state')->get('confirmed')->getValue()) {
      return new ChecklistItemResult(ChecklistAttempt::WAITING, ['awaiting_confirmation' => TRUE], reason: 'Waiting for operation confirmation.');
    }
    // Call the provider after claiming, outside the result transaction.
    $result = $operation->executeWithoutSaving($communication, $options);
    return new ChecklistItemResult(
      $result->succeeded ? ChecklistAttempt::SUCCEEDED : ChecklistAttempt::FAILED,
      outcomes: ['communication' => $result->communication, 'succeeded' => $result->succeeded],
      reason: $result->succeeded ? 'Communication operation reported success.' : 'Communication operation reported failure; review before retrying.',
      persist: static function () use ($result): void {
        $result->save();
      },
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getActionState(): ?ChecklistActionState {
    if ($this->item->isComplete()) {
      return new ChecklistActionState('complete', (string) $this->t('Communication operation completed.'));
    }
    $state = $this->item->get('state');
    $input = !$this->item->isFailed() && $state->get('awaiting_confirmation')->getValue() && !$state->get('confirmed')->getValue();
    return new ChecklistActionState($input ? 'input' : 'processing', (string) ($input ? $this->t('Confirm the communication operation.') : $this->t('Communication operation pending.')), inputRequired: (bool) $input);
  }

  /**
   * Returns the current confirmation fence without starting work.
   */
  public function inputAttempt(): ?ChecklistAttempt {
    $attempt = $this->journal->latest($this->item);
    return $this->getActionState()?->inputRequired && $attempt?->status === ChecklistAttempt::WAITING ? $attempt : NULL;
  }

  /**
   * Confirms through the same owner/executor and revision checks for HTML/API.
   */
  public function confirm(string $id, int $version): array {
    $this->operation();
    $attempt = $this->inputAttempt();
    if (!$attempt || $attempt->id !== $id || $attempt->version !== $version) {
      throw new ChecklistAttemptConflictException('This confirmation request has changed.');
    }
    $queued = $this->executor->acceptInput($this->item, $attempt, ['confirmed' => TRUE]);
    return ['attempt_id' => $queued->id, 'version' => $queued->version];
  }

  /**
   * {@inheritdoc}
   */
  public function actionOperations(): array {
    $attempt = $this->inputAttempt();
    return $attempt ? [
      'confirm' => [
        'label' => 'Confirm communication operation',
        'parameters_schema' => [
          'type' => 'object',
          'properties' => [
            'attempt_id' => [
              'type' => 'string',
              'const' => $attempt->id,
            ],
            'version' => [
              'type' => 'integer',
              'const' => $attempt->version,
            ],
          ],
          'required' => ['attempt_id', 'version'],
          'additionalProperties' => FALSE,
        ],
      ],
    ] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function executeActionOperation(string $operation, array $parameters): array {
    if ($operation !== 'confirm' || array_diff(array_keys($parameters), ['attempt_id', 'version']) || !is_string($parameters['attempt_id'] ?? NULL) || !is_int($parameters['version'] ?? NULL)) {
      throw new \InvalidArgumentException('Supply the advertised confirmation attempt and version.');
    }
    return $this->confirm($parameters['attempt_id'], $parameters['version']);
  }

}
