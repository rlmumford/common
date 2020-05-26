<?php

namespace Drupal\checklist\Plugin\ChecklistItemHandler;

use Drupal\checklist\Entity\ChecklistItemInterface;

/**
 * Class SimplyCheckableChecklistItemHandler
 *
 * @ChecklistItemHandler(
 *   id = "simply_checkable",
 *   label = @Translation("Simple Checkbox"),
 *   forms = {
 *     "row" = "\Drupal\checklist\PluginForm\SimplyCheckableItemRowForm",
 *   }
 * )
 *
 * @package Drupal\checklist\Plugin\ChecklistItemHandler
 */
class SimplyCheckableChecklistItemHandler extends ChecklistItemHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function getMethod(): string {
    return ChecklistItemInterface::METHOD_MANUAL;
  }

  /**
   * Action the checklist item.
   *
   * @return \Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface
   */
  public function action(): ChecklistItemHandlerInterface {
    // This is a completely manual process. Nothing happens her.
    return $this;
  }
}
