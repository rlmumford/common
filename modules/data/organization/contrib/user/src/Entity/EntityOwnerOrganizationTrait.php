<?php

namespace Drupal\organization_user\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\organization\Entity\EntityOrganizationTrait;
use Drupal\user\EntityOwnerInterface;

trait EntityOwnerOrganizationTrait {
  use EntityOrganizationTrait;

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   */
  public static function getDefaultEntityOrganization(EntityInterface $entity, FieldDefinitionInterface $field_definition) {
    if ($entity instanceof EntityOwnerInterface && $owner = $entity->getOwner()) {
      return $owner->organization[0]->target_id;
    }
    else {
      $user_storage = \Drupal::entityTypeManager()->getStorage('user');
      $owner = $user_storage->load(\Drupal::currentUser()->id());
      $delta = \Drupal::service('session')->get('current_organization', 0);

      return $owner->organization[$delta]->target_id;
    }
  }

}
