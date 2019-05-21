<?php

namespace Drupal\relationships\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Class RelationshipAccessControlHandler
 *
 * @package Drupal\relationships\Entity
 */
class RelationshipAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, FieldItemListInterface $items = NULL) {
    $result = AccessResult::allowedIf(!($operation == 'edit' && in_array($field_definition->getName(), ['tail', 'head']) && $items && !$items->isEmpty()))
      ->addCacheableDependency($field_definition);
    if ($items) {
      $result = $result->addCacheableDependency($items->getEntity());
    }

    return $result;
  }
}
