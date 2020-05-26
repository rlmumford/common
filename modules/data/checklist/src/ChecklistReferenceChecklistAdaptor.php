<?php

namespace Drupal\checklist;

use Drupal\Core\TypedData\TypedData;

class ChecklistReferenceChecklistAdaptor extends TypedData {

  /**
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
      list($field_name, $delta) = explode(':', $key, 2);

      // @todo: Load from tempstore?
      return $entity->{$field_name}[$delta]->checklist;
    }
    else {
      return $entity->{$key}->checklist;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE) {
    return parent::setValue($value, $notify);
  }

}
