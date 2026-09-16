<?php

namespace Drupal\typed_data_plus\Plugin\Condition;

/**
 * Combines conditions using OR.
 *
 * @Condition(
 *   id = "condition_or",
 *   label = @Translation("Any condition (OR)")
 * )
 */
class ConditionOr extends ConditionGroup {

  /**
   * {@inheritdoc}
   */
  protected function combine(array $values): bool {
    return in_array(TRUE, $values, TRUE);
  }

}
