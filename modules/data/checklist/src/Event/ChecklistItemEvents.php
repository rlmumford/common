<?php
/**
 * Created by PhpStorm.
 * User: Rob
 * Date: 27/05/2020
 * Time: 16:24
 */

namespace Drupal\checklist\Event;

final class ChecklistItemEvents {

  const ITEM_COMPLETED = 'checklist_item.completed';
  const ITEM_REVERSED = 'checklist_item.reversed';
  const ITEM_FAILED = 'checklist_item.failed';
  const ITEM_RECOVERED = 'checklist_item.recovered';

}
