<?php

namespace Drupal\checklist;

use Drupal\Core\TypedData\TypedData;

/**
 * Typed data adaptor for checklist references.
 */
class ChecklistReferenceChecklistAdaptor extends TypedData {

  /**
   * The checklist.
   *
   * @var \Drupal\checklist\ChecklistInterface
   */
  protected $checklist;

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    /** @var \Drupal\Core\Field\FieldItemInterface $item */
    $item = $this->getParent();
    $entity = $item->entity;
    $key = $item->checklist_key;

    if (strpos($key, ':')) {
      [$field_name, $delta] = explode(':', $key, 2);

      // @todo Load from tempstore?
      $checklist = $entity->{$field_name}[$delta]->checklist;
    }
    else {
      $checklist = $entity->{$key}->checklist;
    }

    /** @var \Drupal\checklist\ChecklistInterface $checklist */
    if ($item->getEntity()->getEntityTypeId() === 'checklist_item') {
      $checklist->setItem($item->getEntity()->getName(), $item->getEntity());
    }

    return $checklist;
  }

}
