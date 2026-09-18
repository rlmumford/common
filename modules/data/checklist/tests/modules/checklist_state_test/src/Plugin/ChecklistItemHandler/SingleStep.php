<?php

namespace Drupal\checklist_state_test\Plugin\ChecklistItemHandler;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Execution\ChecklistItemResult;
use Drupal\checklist\Plugin\ChecklistItemHandler\AutomaticChecklistItemHandlerBase;
use Drupal\checklist\Plugin\ChecklistItemHandler\ExpectedOutcomeChecklistItemHandlerInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Completes in one call without a working-state definition.
 *
 * @ChecklistItemHandler(
 *   id = "single_step_test",
 *   label = @Translation("Single step test"),
 *   context_definitions = {
 *     "value" = @ContextDefinition("string", required = TRUE)
 *   }
 * )
 */
class SingleStep extends AutomaticChecklistItemHandlerBase implements ExpectedOutcomeChecklistItemHandlerInterface {

  /**
   * Names of items invoked by this handler.
   *
   * @var array
   */
  public static array $calls = [];

  /**
   * Optional clock/failure injection during an invocation.
   *
   * @var \Closure|null
   */
  public static ?\Closure $during = NULL;

  /**
   * {@inheritdoc}
   */
  public function expectedOutcomeDefinitions(): array {
    return ['result' => DataDefinition::create('string')];
  }

  /**
   * {@inheritdoc}
   */
  public function actionIteration(ChecklistAttempt $attempt): ChecklistItemResult {
    static::$calls[] = $this->item->getName();
    if (static::$during) {
      (static::$during)();
    }
    return new ChecklistItemResult(ChecklistAttempt::SUCCEEDED, outcomes: ['result' => $this->getContextValue('value') . '-done']);
  }

}
