<?php

namespace Drupal\checklist\Event;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event on a checklist item.
 */
class ChecklistItemEvent extends Event {

  /**
   * The checklist item.
   *
   * @var \Drupal\checklist\Entity\ChecklistItemInterface
   */
  protected $item;

  /**
   * ChecklistItemEvent constructor.
   *
   * @param \Drupal\checklist\Entity\ChecklistItemInterface $checklist_item
   *   The checklist item.
   */
  public function __construct(ChecklistItemInterface $checklist_item) {
    $this->item = $checklist_item;
  }

  /**
   * Get the checklist item.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface
   *   The checklist item.
   */
  public function getChecklistItem() : ChecklistItemInterface {
    return $this->item;
  }

}
