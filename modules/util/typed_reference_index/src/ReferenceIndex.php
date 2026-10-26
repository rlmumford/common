<?php

namespace Drupal\typed_reference_index;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\typed_data_reference\TypedDataReferenceItemList;

/**
 * Indexes current typed references without interpreting their business meaning.
 */
final class ReferenceIndex {

  /**
   * Constructs the reference index.
   */
  public function __construct(private readonly Connection $database, private readonly EntityTypeManagerInterface $entities, private readonly EntityFieldManagerInterface $fields, private readonly EntityReferences $references) {}

  /**
   * Replaces one source's index inside its SQL entity save transaction.
   */
  public function synchronize(ContentEntityInterface $source): void {
    if (!$source->isDefaultRevision() || !$this->entities->getStorage($source->getEntityTypeId()) instanceof SqlEntityStorageInterface) {
      return;
    }
    $records = [];
    $has_typed_fields = FALSE;
    foreach ($source->getTranslationLanguages() as $langcode => $language) {
      foreach ($source->getTranslation($langcode)->getFields(FALSE) as $name => $field) {
        if (!$field instanceof TypedDataReferenceItemList) {
          continue;
        }
        $has_typed_fields = TRUE;
        foreach ($this->references->collect($field) as $reference) {
          $record = [
            'source_type' => $source->getEntityTypeId(),
            'source_id' => (string) $source->id(),
            'langcode' => $langcode,
            'field_name' => $name,
            'property_path' => $reference['path'],
            'target_type' => $reference['entity_type'],
            'target_id' => $reference['entity_id'],
          ];
          $id = hash('sha256', json_encode($record, JSON_THROW_ON_ERROR));
          $records[$id] = ['id' => $id] + $record;
        }
      }
    }
    if (!$has_typed_fields) {
      return;
    }
    $this->remove($source->getEntityTypeId(), (string) $source->id());
    if ($records) {
      $insert = $this->database->insert('typed_reference_index')->fields(array_keys(reset($records)));
      foreach ($records as $record) {
        $insert->values($record);
      }
      $insert->execute();
    }
  }

  /**
   * Removes a deleted source's derived entries, without retaining a history.
   */
  public function remove(string $type, string $id): void {
    $this->database->delete('typed_reference_index')->condition('source_type', $type)->condition('source_id', $id)->execute();
  }

  /**
   * Returns a composable lookup; callers must apply source and target access.
   *
   * This low-level query intentionally does not grant permission to any entity.
   */
  public function referringSources(string $target_type, string $target_id, string $source_type, string $field_name): SelectInterface {
    return $this->database->select('typed_reference_index', 'typed_reference')->fields('typed_reference', ['source_id'])
      ->condition('target_type', $target_type)->condition('target_id', $target_id)
      ->condition('source_type', $source_type)->condition('field_name', $field_name);
  }

  /**
   * Finds SQL content types with typed fields for bounded rebuilds.
   */
  public function sourceTypes(): array {
    $types = [];
    foreach ($this->fields->getFieldMapByFieldType('typed_data_reference') as $type => $fields) {
      if ($this->entities->getStorage($type) instanceof SqlEntityStorageInterface) {
        $types[] = $type;
      }
    }
    return $types;
  }

  /**
   * Clears derived entries before a full rebuild, never any source values.
   */
  public function clear(): void {
    $this->database->delete('typed_reference_index')->execute();
  }

  /**
   * Rebuilds a bounded source batch, locking each source before reloading it.
   */
  public function rebuild(string $type, ?string $after, int $limit = 50): ?string {
    if (!in_array($type, $this->sourceTypes(), TRUE)) {
      throw new \InvalidArgumentException('The entity type has no SQL typed fields.');
    }
    $definition = $this->entities->getDefinition($type);
    $id_key = $definition->getKey('id');
    $storage = $this->entities->getStorage($type);
    $query = $storage->getQuery()->accessCheck(FALSE)->sort($id_key)->range(0, $limit);
    if ($after !== NULL) {
      $query->condition($id_key, $after, '>');
    }
    $ids = $query->execute();
    foreach ($ids as $id) {
      $transaction = $this->database->startTransaction();
      try {
        $found = $this->database->select($definition->getBaseTable(), 's')->fields('s', [$id_key])->condition($id_key, $id)->forUpdate()->execute()->fetchField();
        if ($found !== FALSE && ($source = $storage->loadUnchanged($id))) {
          $this->synchronize($source);
        }
      }
      catch (\Throwable $exception) {
        $transaction->rollBack();
        throw $exception;
      }
      finally {
        unset($transaction);
      }
    }
    return $ids ? (string) end($ids) : NULL;
  }

}
