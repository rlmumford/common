<?php

namespace Drupal\exec_environment;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Permissions generator service.
 *
 * @package Drupal\exec_environment
 */
class ExecEnvironmentSetCurrentUserPermissions {

  /**
   * The role storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $roleStorage;

  /**
   * ExecEnvironmentSetCurrentUserPermissions constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->roleStorage = $entity_type_manager->getStorage('user_role');
  }

  /**
   * Get the permissions for the set current user environment component.
   *
   * @return array
   *   The permissions.
   */
  public function getPermissions() {
    $perms = [];
    foreach ($this->roleStorage->loadMultiple() as $role) {
      $perms['enter ' . $role->id() . ' user environment'] = [
        'title' => "Enter {$role->label()} users environment",
      ];
    }
    return $perms;
  }

}
