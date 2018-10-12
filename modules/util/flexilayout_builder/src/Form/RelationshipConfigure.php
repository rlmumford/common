<?php

namespace Drupal\flexilayout_builder\Form;

use Drupal\ctools\Form\RelationshipConfigure as CtoolsRelationshipConfigure;

class RelationshipConfigure extends CtoolsRelationshipConfigure {

  /**
   * Document the route name and parameters for redirect after submission.
   *
   * @param array $cached_values
   *
   * @return array In the format of
   * In the format of
   * return ['route.name', ['machine_name' => $this->machine_name, 'step' => 'step_name']];
   */
  protected function getParentRouteInfo($cached_values) {
    // TODO: Implement getParentRouteInfo() method.
  }

  /**
   * Custom logic for setting the conditions array in cached_values.
   *
   * @param $cached_values
   *
   * @param $contexts
   *   The conditions to set within the cached values.
   *
   * @return mixed
   *   Return the $cached_values
   */
  protected function setContexts($cached_values, $contexts) {
    // TODO: Implement setContexts() method.
  }

  /**
   * Custom logic for retrieving the contexts array from cached_values.
   *
   * @param $cached_values
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   */
  protected function getContexts($cached_values) {
    // TODO: Implement getContexts() method.
  }
}
