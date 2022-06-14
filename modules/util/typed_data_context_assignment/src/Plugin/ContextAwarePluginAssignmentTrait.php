<?php

namespace Drupal\typed_data_context_assignment\Plugin;

use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait as CoreContextAwarePluginAssignmentTrait;
use Drupal\Core\Plugin\ContextAwarePluginInterface;

/**
 * Trait for plugins that want to use typed_data_context_assignment.
 */
trait ContextAwarePluginAssignmentTrait {
  use CoreContextAwarePluginAssignmentTrait;

  /**
   * {@inheritdoc}
   */
  protected function addContextAssignmentElement(ContextAwarePluginInterface $plugin, array $contexts) {
    return $this->contextHandler()->getContextAssignmentElement($plugin, $contexts);
  }

}
