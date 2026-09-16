<?php

namespace Drupal\typed_data_context_assignment\Plugin\Condition;

use Drupal\ctools\Plugin\Condition\EntityBundle as CtoolsEntityBundle;
use Drupal\typed_data_context_assignment\Plugin\ContextAwarePluginAssignmentTrait;

/**
 * Override entity bundle condition.
 */
class EntityBundle extends CtoolsEntityBundle {
  use ContextAwarePluginAssignmentTrait;

}
