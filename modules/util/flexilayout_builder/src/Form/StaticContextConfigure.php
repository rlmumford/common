<?php
/**
 * Created by PhpStorm.
 * User: Mumford
 * Date: 12/10/2018
 * Time: 11:36
 */

namespace Drupal\flexilayout_builder\Form;


use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\ctools\Form\ContextConfigure;

class StaticContextConfigure extends ContextConfigure {

  /**
   * Document the route name and parameters for redirect after submission.
   *
   * @param $cached_values
   *
   * @return array
   *   In the format of
   *   return ['route.name', ['machine_name' => $this->machine_name, 'step' => 'step_name]];
   */
  protected function getParentRouteInfo($cached_values) {
    // TODO: Implement getParentRouteInfo() method.
  }

  /**
   * Custom logic for retrieving the contexts array from cached_values.
   *
   * @param $cached_values
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   */
  protected function getContexts($cached_values)
  {
    // TODO: Implement getContexts() method.
  }

  /**
   * Custom logic for adding a context to the cached_values contexts array.
   *
   * @param array $cached_values
   *   The cached_values currently in use.
   * @param string $context_id
   *   The context identifier.
   * @param \Drupal\Core\Plugin\Context\ContextInterface $context
   *   The context to add or update within the cached values.
   *
   * @return mixed
   *   Return the $cached_values
   */
  protected function addContext($cached_values, $context_id, ContextInterface $context)
  {
    // TODO: Implement addContext() method.
  }

  /**
   * Custom "exists" logic for the context to be created.
   *
   * @param string $value
   *   The name of the context.
   * @param $element
   *   The machine_name element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return bool
   *   Return true if a context of this name exists.
   */
  public function contextExists($value, $element, $form_state)
  {
    // TODO: Implement contextExists() method.
  }

  /**
   * Determines if the machine_name should be disabled.
   *
   * @param $cached_values
   *
   * @return bool
   */
  protected function disableMachineName($cached_values, $machine_name)
  {
    // TODO: Implement disableMachineName() method.
  }
}
