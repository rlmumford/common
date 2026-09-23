<?php

namespace Drupal\checklist_template_test\Plugin\EntityTemplate\Component;

use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\entity_template\Execution\GatherResult;
use Drupal\entity_template\Plugin\EntityTemplate\Component\PropertyValue;

/**
 * Exercises continuation, errors and changes while preparation is in flight.
 *
 * @EntityTemplateComponent(
 *   id = "checklist_pending",
 *   label = @Translation("Checklist pending template"),
 *   applies_to = {"*"}
 * )
 */
class Pending extends PropertyValue {

  /**
   * Optional in-flight mutation controlled by a test.
   */
  public static ?\Closure $during = NULL;

  /**
   * {@inheritdoc}
   */
  public function gather(TypedDataInterface $target, array $contexts, array $state, ?int $remainingMilliseconds): GatherResult {
    if (static::$during) {
      (static::$during)();
    }
    if (!$state) {
      return GatherResult::pending(['run' => 'retained-request']);
    }
    if ($state['run'] !== 'retained-request') {
      throw new \LogicException('The external request was lost.');
    }
    if ($this->configuration['value'] === 'fail') {
      throw new \RuntimeException('Private provider diagnostics');
    }
    return GatherResult::complete($contexts['title']->getContextValue());
  }

}
