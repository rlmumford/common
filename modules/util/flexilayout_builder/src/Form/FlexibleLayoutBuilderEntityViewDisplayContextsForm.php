<?php

namespace Drupal\flexilayout_builder\Form;

use Drupal\ctools\Form\ManageContext;

class FlexibleLayoutBuilderEntityViewDisplayContextsForm extends ManageContext {

  /**
   * Return a subclass of '\Drupal\ctools\Form\ContextConfigure'.
   *
   * The ContextConfigure class is designed to be subclassed with custom
   * route information to control the modal/redirect needs of your use case.
   *
   * @return string
   */
  protected function getContextClass($cached_values) {
    return StaticContextConfigure::class;
  }

  /**
   * Return a subclass of '\Drupal\ctools\Form\RelationshipConfigure'.
   *
   * The RelationshipConfigure class is designed to be subclassed with custom
   * route information to control the modal/redirect needs of your use case.
   *
   * @return string
   */
  protected function getRelationshipClass($cached_values) {
    return RelationshipConfigure::class;
  }

  /**
   * The route to which context 'add' actions should submit.
   *
   * @return string
   */
  protected function getContextAddRoute($cached_values) {
    // TODO: Implement getContextAddRoute() method.
  }

  /**
   * The route to which relationship 'add' actions should submit.
   *
   * @return string
   */
  protected function getRelationshipAddRoute($cached_values) {
    // TODO: Implement getRelationshipAddRoute() method.
  }

  /**
   * Provide the tempstore id for your specified use case.
   *
   * @return string
   */
  protected function getTempstoreId() {
    // TODO: Implement getTempstoreId() method.
  }

  /**
   * Returns the contexts already available in the wizard.
   *
   * @param mixed $cached_values
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   */
  protected function getContexts($cached_values) {
    // TODO: Implement getContexts() method.
  }

  /**
   * @param mixed $cached_values
   * @param string $machine_name
   * @param string $row
   *
   * @return array
   */
  protected function getContextOperationsRouteInfo($cached_values, $machine_name, $row) {
    // TODO: Implement getContextOperationsRouteInfo() method.
  }

  /**
   * @param mixed $cached_values
   * @param string $machine_name
   * @param string $row
   *
   * @return array
   */
  protected function getRelationshipOperationsRouteInfo($cached_values, $machine_name, $row) {
    // TODO: Implement getRelationshipOperationsRouteInfo() method.
  }

  /**
   * @param mixed $cached_values
   * @param string $row
   *
   * @return bool
   */
  protected function isEditableContext($cached_values, $row) {
    // TODO: Implement isEditableContext() method.
  }
}
