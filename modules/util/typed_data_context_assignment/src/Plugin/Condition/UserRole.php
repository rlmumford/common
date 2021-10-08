<?php

namespace Drupal\typed_data_context_assignment\Plugin\Condition;

use Drupal\typed_data_context_assignment\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\user\Plugin\Condition\UserRole as CoreUserRole;

/**
 * Override core UserRole plugin to use our better plugin assignment trait.
 *
 * @package Drupal\typed_data_context_assignment\Plugin\Condition
 */
class UserRole extends CoreUserRole {
  use ContextAwarePluginAssignmentTrait;

}
