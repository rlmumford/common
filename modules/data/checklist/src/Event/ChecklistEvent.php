<?php

namespace Drupal\checklist\Event;

use Drupal\checklist\ChecklistInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Checklist Event class for dispatched checklist events.
 *
 * Events related to a specific checklist item use ChecklistItemEvent.
 */
class ChecklistEvent extends Event {

  /**
   * The checklist.
   *
   * @var \Drupal\checklist\ChecklistInterface
   */
  protected $checklist;

  /**
   * ChecklistEvent constructor.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The checklist.
   */
  public function __construct(ChecklistInterface $checklist) {
    $this->checklist = $checklist;
  }

  /**
   * Get the checklist.
   *
   * @return \Drupal\checklist\ChecklistInterface
   *   The checklist.
   */
  public function getChecklist() : ChecklistInterface {
    return $this->checklist;
  }

}
