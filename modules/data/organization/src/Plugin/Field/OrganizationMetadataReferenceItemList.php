<?php

namespace Drupal\organization\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\organization\Entity\Organization;

class OrganizationMetadataReferenceItemList extends EntityReferenceFieldItemList {

  /**
   * @param \Drupal\organization\Entity\Organization $organization
   * @param bool $create
   *
   * @return \Drupal\organization\Plugin\Field\FieldType\OrganizationMetadataReferenceItem
   */
  public function getOrganizationItem(Organization $organization, $create = TRUE) {
    foreach ($this as $item) {
      if ($item === $organization->id()) {
        return $item;
      }
    }

    if ($create) {
      return $this->appendItem(['target_id' => $organization->id()]);
    }

    return NULL;
  }

}
