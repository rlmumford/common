<?php

namespace Drupal\checklist\Event;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Symfony\Component\EventDispatcher\Event;

class ChecklistItemEvent extends Event {

  /**
   * @var \Drupal\checklist\Entity\ChecklistItemInterface
   */
  protected $item;

  /**
   * ChecklistItemEvent constructor.
   *
   * @param \Drupal\checklist\Entity\ChecklistItemInterface $checklist_item
   */
  public function __construct(ChecklistItemInterface $checklist_item) {
    $this->item = $checklist_item;
  }

  /**
   * Get the checklist item.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface
   */
  public function getChecklistItem() : ChecklistItemInterface {
    return $this->item;
  }

}
