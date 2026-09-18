<?php

namespace Drupal\checklist_state_test\Plugin\ChecklistItemHandler;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Execution\ChecklistItemResult;
use Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ContextAwareChecklistItemHandlerBase;
use Drupal\checklist\Plugin\ChecklistItemHandler\ExpectedOutcomeChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\IterativeChecklistItemHandlerInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\checklist\Plugin\ChecklistItemHandler\StatefulChecklistItemHandlerInterface;

/**
 * Runs two iterations and exposes an in-flight change seam for kernel tests.
 *
 * @ChecklistItemHandler(
 *   id = "iteration_test",
 *   label = @Translation("Iteration test"),
 *   context_definitions = {
 *     "value" = @ContextDefinition("string", required = TRUE)
 *   }
 * )
 */
class Iteration extends ContextAwareChecklistItemHandlerBase implements IterativeChecklistItemHandlerInterface, ExpectedOutcomeChecklistItemHandlerInterface, StatefulChecklistItemHandlerInterface {

  /**
   * Calls observed outside the result transaction.
   *
   * @var array
   */
  public static array $calls = [];

  /**
   * In-flight changes to simulate after the provider starts.
   *
   * @var \Closure|null
   */
  public static ?\Closure $during = NULL;

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
      'run_id' => DataDefinition::create('string'),
      'completed' => DataDefinition::create('integer'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function expectedOutcomeDefinitions(): array {
    return ['result' => DataDefinition::create('string')];
  }

  /**
   * {@inheritdoc}
   */
  public function action(): ChecklistItemHandlerInterface {
    throw new \LogicException('The legacy action path must not run iterative work.');
  }

  /**
   * {@inheritdoc}
   */
  public function actionIteration(ChecklistAttempt $attempt): ChecklistItemResult {
    $completed = $this->item->get('state')->get('completed')->getCastedValue() ?? 0;
    static::$calls[] = [
      (int) \Drupal::currentUser()->id(),
      \Drupal::database()->inTransaction(),
      $this->item->get('state')->get('run_id')->getValue(),
      $this->getContextValue('value'),
      $attempt->id,
    ];
    if (static::$during) {
      (static::$during)();
    }
    if (!empty($this->configuration['throw'])) {
      throw new \RuntimeException('Secret provider payload');
    }
    if (!empty($this->configuration['fail'])) {
      return new ChecklistItemResult(ChecklistAttempt::FAILED, ['run_id' => 'failed-run'], reason: 'Provider declined');
    }
    if (!empty($this->configuration['invalid_value'])) {
      return new ChecklistItemResult(ChecklistAttempt::WAITING, [
        'run_id' => 'must-not-save',
        'completed' => 'not an integer',
      ]);
    }
    if (!empty($this->configuration['invalid'])) {
      return new ChecklistItemResult(ChecklistAttempt::WAITING, ['unknown' => 'bad']);
    }
    if (!$completed) {
      return new ChecklistItemResult(ChecklistAttempt::WAITING, ['run_id' => 'provider-123', 'completed' => 1], delay: 5);
    }
    return new ChecklistItemResult(ChecklistAttempt::SUCCEEDED, outcomes: ['result' => $this->getContextValue('value')]);
  }

}
