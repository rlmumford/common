<?php

namespace Drupal\typed_data_plus\Plugin\Condition;

/**
 * Combines conditions using AND.
 *
 * @Condition(
 *   id = "condition_and",
 *   label = @Translation("All conditions (AND)")
 * )
 */
class ConditionAnd extends ConditionGroup {

  /**
   * {@inheritdoc}
   */
  protected function combine(array $values): bool {
    return !in_array(FALSE, $values, TRUE);
  }

}
