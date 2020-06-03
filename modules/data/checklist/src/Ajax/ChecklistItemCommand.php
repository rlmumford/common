<?php

namespace Drupal\checklist\Ajax;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Core\Ajax\CommandInterface;

class ChecklistItemCommand implements CommandInterface {

  /**
   * @var string
   */
  protected $command = NULL;

  /**
   * @var string
   */
  protected $selector;

  /**
   * @var string
   */
  protected $ciname;

  /**
   * StartNextItemCommand constructor.
   *
   * @param $checklist_item
   * @param null $selector
   */
  public function __construct($checklist_item, $selector = NULL, $command = NULL) {
    if ($command) {
      $this->command = $command;
    }

    if ($checklist_item instanceof ChecklistItemInterface) {
      /** @var \Drupal\checklist\ChecklistInterface $checklist */
      $checklist = $checklist_item->checklist->checklist;

      $this->ciname = $checklist_item->getName();
      $this->selector = "ul.{$checklist->getEntity()->getEntityTypeId()}-".
        str_replace(':', '--', $checklist->getKey()).
        "-checklist";
    }
    else if (is_string($checklist_item)) {
      $this->ciname = $checklist_item;
      $this->selector = $selector;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => $this->getCommand(),
      'selector' => $this->selector,
      'ciname' => $this->ciname,
    ];
  }

  /**
   * Get the command
   *
   * @return string
   */
  protected function getCommand() {
    return $this->command;
  }

}
