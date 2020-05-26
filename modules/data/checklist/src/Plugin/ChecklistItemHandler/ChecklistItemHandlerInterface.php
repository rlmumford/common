<?php

namespace Drupal\checklist\Plugin\ChecklistItemHandler;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;

interface ChecklistItemHandlerInterface extends PluginInspectionInterface, PluginWithFormsInterface, ConfigurableInterface {

  /**
   * Set the checklist item on this handler.
   *
   * @param \Drupal\checklist\Entity\ChecklistItemInterface $item
   *
   * @return \Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface
   */
  public function setItem(ChecklistItemInterface $item) : ChecklistItemHandlerInterface;

  /**
   * Get the checklist item object.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface
   */
  public function getItem() : ChecklistItemInterface;

  /**
   * Set the name
   *
   * @param string $name
   *
   * @return \Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface
   */
  public function setName(string $name) : ChecklistItemHandlerInterface;

  /**
   * Get the name of the checklist item.
   *
   * @return null|string
   */
  public function getName() : ?string;

  /**
   * Get the method.
   *
   * @return string
   */
  public function getMethod() : string;

  /**
   * Check whether this is applicable.
   *
   * @return bool|null
   */
  public function isApplicable() : ?bool;

  /**
   * Is this item required
   *
   * @return bool
   */
  public function isRequired() : bool;

  /**
   * Is this item actionable.
   *
   * @return bool
   */
  public function isActionable() : bool;

  /**
   * Action the checklist item.
   *
   * @return \Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface
   */
  public function action() : ChecklistItemHandlerInterface;

  /**
   * Build the configuration summary.
   *
   * @return array
   */
  public function buildConfigurationSummary() : array;

}
