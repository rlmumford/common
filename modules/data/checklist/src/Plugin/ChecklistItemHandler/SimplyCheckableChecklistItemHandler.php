<?php

namespace Drupal\checklist\Plugin\ChecklistItemHandler;

use Drupal\checklist\Entity\ChecklistItemInterface;

/**
 * Checklist item handler that shows a simple checkbox.
 *
 * @ChecklistItemHandler(
 *   id = "simply_checkable",
 *   label = @Translation("Simple Checkbox"),
 *   forms = {
 *     "row" = "\Drupal\checklist\PluginForm\SimplyCheckableItemRowForm",
 *     "configure" = "\Drupal\checklist\PluginForm\SimplyCheckableItemConfigureForm",
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
   * {@inheritdoc}
   */
  public function action(): ChecklistItemHandlerInterface {
    // This is a completely manual process. Nothing happens here.
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'reversible' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary(): array {
    return [
      '#markup' => $this->getPluginDefinition()['label'],
    ];
  }

}
