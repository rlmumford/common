<?php

namespace Drupal\checklist\Event;

/**
 * Checklist item event names.
 */
final class ChecklistItemEvents {

  const ITEM_COMPLETED = 'checklist_item.completed';
  const ITEM_REVERSED = 'checklist_item.reversed';
  const ITEM_FAILED = 'checklist_item.failed';
  const ITEM_RECOVERED = 'checklist_item.recovered';

}
