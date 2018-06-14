<?php

namespace Drupal\sowlo_role;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\entity\EntityPermissionProvider;

/**
 * Providers Job Role entity permissions.
 *
 * Extends the Entity API permission provider to support bundle based view
 * permissions.
 */
class JobRolePermissionProvider extends EntityPermissionProvider {
}