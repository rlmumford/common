<?php

namespace Drupal\checklist\Ajax;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Core\Ajax\CommandInterface;

/**
 * Base class for checklist item ajax commands.
 */
class ChecklistItemCommand implements CommandInterface {

  /**
   * The command.
   *
   * @var string
   */
  protected $command;

  /**
   * The selector.
   *
   * @var string
   */
  protected $selector;

  /**
   * The checklist item name.
   *
   * @var string
   */
  protected $ciname;

  /**
   * ChecklistItemCommand constructor.
   *
   * @param \Drupal\checklist\Entity\ChecklistItemInterface|string $checklist_item
   *   The checklist item or name.
   * @param string|null $selector
   *   The selector.
   * @param string|null $command
   *   The command.
   */
  public function __construct($checklist_item, $selector = NULL, $command = NULL) {
    if ($command) {
      $this->command = $command;
    }

    if ($checklist_item instanceof ChecklistItemInterface) {
      /** @var \Drupal\checklist\ChecklistInterface $checklist */
      $checklist = $checklist_item->checklist->checklist;

      $this->ciname = $checklist_item->getName();
      $this->selector = "ul.{$checklist->getEntity()->getEntityTypeId()}-" .
        str_replace(':', '--', $checklist->getKey()) .
        "-checklist";
    }
    elseif (is_string($checklist_item)) {
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
   * Get the command.
   *
   * @return string
   *   The js command.
   */
  protected function getCommand() {
    return $this->command;
  }

}
