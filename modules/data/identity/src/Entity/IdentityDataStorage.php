<?php

namespace Drupal\identity\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

class IdentityDataStorage extends SqlContentEntityStorage {

  /**
   * {@inheritdoc}
   */
  protected function getQueryServiceName() {
    return 'identity.query.sql';
  }

  /**
   * {@inheritdoc}
   */
  public function doPreSave(EntityInterface $entity) {
    /** @var \Drupal\identity\Entity\IdentityData $entity */
    $return = parent::doPreSave($entity);

    $plugin = $entity->getClass();
    if (is_callable([$plugin, 'preSaveData'])) {
      $plugin->preSaveData($entity);
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function doPostSave(EntityInterface $entity, $update) {
    /** @var \Drupal\identity\Entity\IdentityData $entity */
    $plugin = $entity->getClass();
    if (is_callable([$plugin, 'postSaveData'])) {
      $plugin->postSaveData($entity);
    }

    parent::doPostSave($entity, $update);
  }

  /**
   * {@inheritdoc}
   */
  protected function invokeStorageLoadHook(array &$entities) {
    /** @var \Drupal\identity\Entity\IdentityData $entity */
    foreach ($entities as $entity) {
      $plugin = $entity->getClass();
      if (is_callable([$plugin, 'storageLoadData'])) {
        $plugin->storageLoadData($entity);
      }
    }

    parent::invokeStorageLoadHook($entities);
  }

  /**
   * {@inheritDoc}
   */
  protected function buildQuery($ids, $revision_ids = FALSE) {
    $query = $this->database->select($this->baseTable, 'base');

    $query->addTag($this->entityTypeId . '_load_multiple');

    if ($revision_ids) {
      $query->join($this->revisionTable, 'revision', "[revision].[{$this->idKey}] = [base].[{$this->idKey}] AND [revision].[{$this->revisionKey}] IN (:revisionIds[])", [':revisionIds[]' => $revision_ids]);
    }

    // Add fields from the {entity} table.
    $table_mapping = $this->getTableMapping();
    $entity_fields = $table_mapping->getAllColumns($this->baseTable);

    if ($this->revisionTable && $revision_ids) {
      // Add all fields from the {entity_revision} table.
      $entity_revision_fields = $table_mapping->getAllColumns($this->revisionTable);
      $entity_revision_fields = array_combine($entity_revision_fields, $entity_revision_fields);
      // The ID field is provided by entity, so remove it.
      unset($entity_revision_fields[$this->idKey]);

      // Remove all fields from the base table that are also fields by the same
      // name in the revision table.
      $entity_field_keys = array_flip($entity_fields);
      foreach ($entity_revision_fields as $name) {
        if (isset($entity_field_keys[$name])) {
          unset($entity_fields[$entity_field_keys[$name]]);
        }
      }
      $query->fields('revision', $entity_revision_fields);

      // Compare revision ID of the base and revision table, if equal then this
      // is the default revision.
      $query->addExpression('CASE [base].[' . $this->revisionKey . '] WHEN [revision].[' . $this->revisionKey . '] THEN 1 ELSE 0 END', 'isDefaultRevision');
    }

    $query->fields('base', $entity_fields);

    if ($ids) {
      $query->condition("base.{$this->idKey}", $ids, 'IN');
    }

    return $query;
  }
}
