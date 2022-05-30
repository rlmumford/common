<?php

namespace Drupal\checklist\Plugin\ChecklistItemHandler;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Checklist item handler for broken configuration.
 *
 * @ChecklistItemHandler(
 *   id = "missing",
 *   label = @Translation("Broken/Missing Handler"),
 *   category = @Translation("Other"),
 *   forms = {
 *     "row" = "\Drupal\checklist\PluginForm\SimplyCheckableItemRowForm",
 *     "configure" = "\Drupal\checklist\PluginForm\SimplyCheckableItemConfigureForm",
 *   }
 * )
 */
class Missing extends ChecklistItemHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function getMethod(): string {
    return ChecklistItemInterface::METHOD_BROKEN;
  }

  /**
   * {@inheritdoc}
   */
  public function action(): ChecklistItemHandlerInterface {
    throw new PluginException("No handler available.");
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary(): array {
    return [
      '#markup' => new TranslatableMarkup("Broken or missing handler."),
    ];
  }

}
