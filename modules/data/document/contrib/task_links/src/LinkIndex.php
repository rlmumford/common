<?php

namespace Drupal\document_task_links;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\task\TaskInterface;
use Drupal\task_context\EntityReferences;
use Drupal\typed_data_reference\TypedDataReferenceItemList;

/**
 * Maintains a query index and observations of saved document relationships.
 *
 * Saved references remain authoritative. This is not a revision pin or a
 * complete execution audit: observations start when tracking is installed.
 */
final class LinkIndex {

  /**
   * Constructs the index.
   */
  public function __construct(private readonly Connection $database, private readonly EntityTypeManagerInterface $entities, private readonly EntityReferences $references, private readonly TimeInterface $time, private readonly ModuleHandlerInterface $modules) {}

  /**
   * Resolves the actual task host from the checklist reference.
   */
  public function task(EntityInterface $source): ?TaskInterface {
    if ($source instanceof TaskInterface) {
      return $source;
    }
    if ($source->getEntityTypeId() === 'checklist_item' && $source->get('checklist')->getSetting('target_type') === 'task') {
      return $source->get('checklist')->entity;
    }
    return NULL;
  }

  /**
   * Lets consumers enforce a boundary independently of the current viewer.
   */
  public function compatible(TaskInterface $task, EntityInterface $document): bool {
    $allowed = TRUE;
    $this->modules->alter('document_task_links_compatible', $allowed, $task, $document);
    return $allowed;
  }

  /**
   * Reconciles links inside the source entity's SQL save transaction.
   */
  public function synchronize(EntityInterface $source): void {
    if ($source instanceof RevisionableInterface && !$source->isDefaultRevision()) {
      return;
    }
    $task = $this->task($source);
    if (!$task || !$task->id()) {
      $this->delete($source);
      return;
    }
    $source_type = $source->getEntityTypeId();
    $now = $this->time->getCurrentTime();
    $existing = $this->database->select('document_task_link', 'l')->fields('l')
      ->condition('source_type', $source_type)->condition('source_id', $source->id())
      ->isNotNull('active_key')->forUpdate()->execute()->fetchAllAssoc('active_key', \PDO::FETCH_ASSOC);
    $current = [];
    $references = iterator_to_array($this->references($source), FALSE);
    $documents = $this->entities->getStorage('document')->loadMultiple(array_unique(array_column($references, 'document_id')));
    foreach ($references as $reference) {
      $document = $documents[$reference['document_id']] ?? NULL;
      if (!$document || !$this->compatible($task, $document)) {
        continue;
      }
      $record = [
        'task_id' => (int) $task->id(),
        'document_id' => (int) $document->id(),
        'source_type' => $source_type,
        'source_id' => (int) $source->id(),
        'field_name' => $reference['field_name'],
        'path' => $reference['path'],
        'relation' => $source_type === 'task' ? 'input' : 'output',
      ];
      $key = hash('sha256', json_encode($record, JSON_THROW_ON_ERROR));
      if (isset($current[$key])) {
        continue;
      }
      $current[$key] = TRUE;
      if (!isset($existing[$key])) {
        $record += [
          'active_key' => $key,
          'observed_revision' => $document->getRevisionId(),
          'first_seen' => $now,
        ];
        $this->database->insert('document_task_link')->fields($record)->execute();
      }
    }
    $removed = array_diff_key($existing, $current);
    if ($removed) {
      $this->database->update('document_task_link')->fields(['active_key' => NULL, 'removed' => $now])
        ->condition('id', array_column($removed, 'id'), 'IN')->execute();
    }
  }

  /**
   * Collects direct document fields, typed contexts and saved outcomes only.
   */
  private function references(FieldableEntityInterface $source): iterable {
    $fields = $source instanceof TaskInterface ? $source->getFields(FALSE) : ['outcomes' => $source->get('outcomes')];
    foreach ($fields as $name => $field) {
      if ($field->getFieldDefinition()->isComputed()) {
        continue;
      }
      if ($field instanceof TypedDataReferenceItemList && in_array($name, ['context', 'outcomes'], TRUE)) {
        foreach ($this->references->collect($field) as $reference) {
          if ($reference['entity_type'] === 'document') {
            yield ['document_id' => $reference['entity_id'], 'field_name' => $name, 'path' => $reference['path']];
          }
        }
      }
      elseif ($source instanceof TaskInterface && $field->getSetting('target_type') === 'document') {
        foreach ($field as $delta => $item) {
          if ($item->target_id) {
            yield ['document_id' => $item->target_id, 'field_name' => $name, 'path' => (string) $delta];
          }
        }
      }
    }
  }

  /**
   * Removes deleted task/document identities, closing deleted item outputs.
   */
  public function delete(EntityInterface $entity): void {
    $type = $entity->getEntityTypeId();
    if (in_array($type, ['task', 'document'], TRUE)) {
      $this->database->delete('document_task_link')->condition($type . '_id', $entity->id())->execute();
    }
    elseif ($type === 'checklist_item') {
      $this->database->update('document_task_link')->fields([
        'active_key' => NULL,
        'removed' => $this->time->getCurrentTime(),
      ])
        ->condition('source_type', $type)->condition('source_id', $entity->id())->isNotNull('active_key')->execute();
    }
  }

  /**
   * Returns observations for one document/task pair, or for a task's documents.
   */
  public function observations(int $task_id, ?int $document_id = NULL): array {
    $query = $this->database->select('document_task_link', 'l')->fields('l')->condition('task_id', $task_id);
    if ($document_id !== NULL) {
      $query->condition('document_id', $document_id);
    }
    return $query->orderBy('id', 'DESC')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Reconciles a bounded batch, locking sources before loading saved values.
   *
   * Returns the last visited ID, or NULL when this source type is exhausted.
   * Repeating a rebuild preserves historical observations and existing dates.
   */
  public function backfill(string $type, int $after, int $limit = 50): ?int {
    if (!in_array($type, ['task', 'checklist_item'], TRUE)) {
      throw new \InvalidArgumentException('Unsupported relationship source.');
    }
    $storage = $this->entities->getStorage($type);
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('id', $after, '>')->sort('id')->range(0, $limit)->execute();
    foreach ($ids as $id) {
      $transaction = $this->database->startTransaction();
      try {
        $found = $this->database->select($type, 's')->fields('s', ['id'])->condition('id', $id)->forUpdate()->execute()->fetchField();
        if ($found && ($source = $storage->loadUnchanged($id))) {
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
    return $ids ? (int) end($ids) : NULL;
  }

}
