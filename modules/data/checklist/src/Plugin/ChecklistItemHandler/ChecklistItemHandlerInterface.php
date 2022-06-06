<?php

namespace Drupal\checklist\Plugin\ChecklistItemHandler;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;

/**
 * Interface for checklist item handlers.
 *
 * Checklist item handlers provide and execute the logic of checklist items.
 * Handlers that are interactive, i.e. they display a form to be used by the
 * user to complete the activity represented by the checklist itm, should
 * implement InteractiveChecklistItemHandlerInterface.
 * Handlers that require contexts can define context_definitions in the plugin
 * definition and should implement ContextAwarePluginInterface.
 * Handlers that provide new data should implement
 * ExpectedOutcomeChecklistItemHandler.
 */
interface ChecklistItemHandlerInterface extends PluginInspectionInterface, PluginWithFormsInterface, ConfigurableInterface {

  /**
   * Set the checklist item on this handler.
   *
   * @param \Drupal\checklist\Entity\ChecklistItemInterface $item
   *   The checklist item.
   *
   * @return $this
   */
  public function setItem(ChecklistItemInterface $item) : ChecklistItemHandlerInterface;

  /**
   * Get the checklist item object.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface
   *   The checklist item.
   */
  public function getItem() : ChecklistItemInterface;

  /**
   * Set the name.
   *
   * @param string $name
   *   The name of the checklist item.
   *
   * @return $this
   */
  public function setName(string $name) : ChecklistItemHandlerInterface;

  /**
   * Get the name of the checklist item.
   *
   * @return null|string
   *   The name of the checklist item.
   */
  public function getName() : ?string;

  /**
   * Get the method.
   *
   * @return string
   *   The method of the checklist item.
   */
  public function getMethod() : string;

  /**
   * Check whether this is applicable.
   *
   * @return bool|null
   *   True if it is applicable, False otherwise, Null if we don't now yet.
   */
  public function isApplicable() : ?bool;

  /**
   * Is this item required.
   *
   * @return bool
   *   True if its is required, False otherwise.
   */
  public function isRequired() : bool;

  /**
   * Is this item actionable.
   *
   * @return bool
   *   True if the item is actionable, False otherwise.
   */
  public function isActionable() : bool;

  /**
   * Action the checklist item.
   *
   * @return $this
   */
  public function action() : ChecklistItemHandlerInterface;

  /**
   * Build the configuration summary.
   *
   * @return array
   *   The configuration summary build array.
   */
  public function buildConfigurationSummary() : array;

  /**
   * Finalize any placeholders.
   */
  public function finalizePlaceholders();

}
