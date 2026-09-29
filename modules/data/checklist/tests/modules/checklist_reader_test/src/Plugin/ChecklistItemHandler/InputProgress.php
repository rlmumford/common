<?php

namespace Drupal\checklist_reader_test\Plugin\ChecklistItemHandler;

use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Attempt\ChecklistAttemptJournal;
use Drupal\checklist\ChecklistActionState;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Execution\ChecklistItemExecutor;
use Drupal\checklist\Execution\ChecklistItemResult;
use Drupal\checklist\Plugin\ChecklistItemHandler\ActionOperationsChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ActionStateChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerBase;
use Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ExpectedOutcomeChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\IterativeChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\StatefulChecklistItemHandlerInterface;
use Drupal\Core\TypedData\DataDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Example: automatic extraction pauses for a reference, then resumes.
 *
 * @ChecklistItemHandler(
 *   id = "input_progress_example",
 *   label = @Translation("Document extraction with input"),
 *   forms = {
 *     "action" = "\Drupal\checklist_reader_test\PluginForm\InputProgressForm"
 *   }
 * )
 */
class InputProgress extends ChecklistItemHandlerBase implements IterativeChecklistItemHandlerInterface, StatefulChecklistItemHandlerInterface, ExpectedOutcomeChecklistItemHandlerInterface, ActionStateChecklistItemHandlerInterface, ActionOperationsChecklistItemHandlerInterface {

  /**
   * Audited input submission and automatic execution.
   *
   * @var \Drupal\checklist\Execution\ChecklistItemExecutor
   */
  protected ChecklistItemExecutor $executor;

  /**
   * Current attempt identity and version.
   *
   * @var \Drupal\checklist\Attempt\ChecklistAttemptJournal
   */
  protected ChecklistAttemptJournal $journal;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->executor = $container->get('checklist.item_executor');
    $plugin->journal = $container->get('checklist.attempt_journal');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary(): array {
    return ['#plain_text' => $this->t('Demonstrates automatic work requesting a document reference.')];
  }

  /**
   * {@inheritdoc}
   */
  public function getMethod(): string {
    return ChecklistItemInterface::METHOD_AUTO;
  }

  /**
   * {@inheritdoc}
   */
  public function stateDefinitions(): array {
    return [
      'started' => DataDefinition::create('boolean'),
      'reference' => DataDefinition::create('string'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function expectedOutcomeDefinitions(): array {
    return ['reference' => DataDefinition::create('string')];
  }

  /**
   * {@inheritdoc}
   */
  public function action(): ChecklistItemHandlerInterface {
    throw new \LogicException('Use the item executor for iterative work.');
  }

  /**
   * {@inheritdoc}
   */
  public function actionIteration(ChecklistAttempt $attempt): ChecklistItemResult {
    $reference = $this->item->get('state')->get('reference')->getValue();
    if (!$reference) {
      // A real handler starts/polls its provider here, then saves its run ID.
      return new ChecklistItemResult(ChecklistAttempt::WAITING, ['started' => TRUE], reason: 'A document reference is needed.');
    }
    return new ChecklistItemResult(ChecklistAttempt::SUCCEEDED, outcomes: ['reference' => $reference]);
  }

  /**
   * {@inheritdoc}
   */
  public function getActionState(): ?ChecklistActionState {
    if ($this->item->isComplete()) {
      return new ChecklistActionState('complete', 'Document information extracted.', 5, 5);
    }
    $state = $this->item->get('state');
    $needs_input = $state->get('started')->getValue() && !$state->get('reference')->getValue();
    return new ChecklistActionState(
      $needs_input ? 'input' : 'processing',
      $needs_input ? 'Four documents processed. Supply the reference for the remaining document.' : 'Processing document information.',
      $state->get('started')->getValue() ? 4 : 0,
      5,
      inputRequired: (bool) $needs_input,
    );
  }

  /**
   * Returns the attempt only while input can be submitted.
   */
  public function inputAttempt(): ?ChecklistAttempt {
    $attempt = $this->journal->latest($this->item);
    return $this->getActionState()?->inputRequired && $attempt?->status === ChecklistAttempt::WAITING ? $attempt : NULL;
  }

  /**
   * Validates domain input for both form validation and operation submission.
   */
  public function validateReference(string $reference): void {
    if (trim($reference) === '') {
      throw new \InvalidArgumentException('Enter a document reference.');
    }
  }

  /**
   * Shared form/API domain validation and fenced working-state update.
   */
  public function supplyReference(string $reference, string $attempt_id, int $version): array {
    $this->validateReference($reference);
    $attempt = $this->journal->load($attempt_id);
    if (!$attempt || $attempt->version !== $version) {
      throw new ChecklistAttemptConflictException('This input request has changed. Reload it before submitting.');
    }
    $queued = $this->executor->acceptInput($this->item, $attempt, ['reference' => trim($reference)]);
    return ['attempt_id' => $queued->id, 'version' => $queued->version];
  }

  /**
   * {@inheritdoc}
   */
  public function actionOperations(): array {
    $attempt = $this->inputAttempt();
    if (!$attempt) {
      return [];
    }
    return [
      'supply_reference' => [
        'label' => 'Supply document reference',
        'parameters_schema' => [
          'type' => 'object',
          'properties' => [
            'reference' => ['type' => 'string', 'pattern' => '\\S'],
            'attempt_id' => ['type' => 'string', 'const' => $attempt->id],
            'version' => ['type' => 'integer', 'const' => $attempt->version],
          ],
          'required' => ['reference', 'attempt_id', 'version'],
          'additionalProperties' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function executeActionOperation(string $operation, array $parameters): array {
    if ($operation !== 'supply_reference') {
      throw new \InvalidArgumentException('Unknown input operation.');
    }
    return $this->supplyReference($parameters['reference'], $parameters['attempt_id'], $parameters['version']);
  }

}
