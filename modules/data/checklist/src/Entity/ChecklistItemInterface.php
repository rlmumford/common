<?php

namespace Drupal\checklist\Entity;

use Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Interface for checklist item entities.
 */
interface ChecklistItemInterface extends EntityInterface {

  /**
   * Status constants.
   */
  const STATUS_COMPLETE = 'complete';
  const STATUS_INCOMPLETE = 'incomplete';
  const STATUS_FAILED = 'failed';
  const STATUS_NA = 'na';

  /**
   * Method constants.
   *
   * These are the methods that can be used to complete or fail a checklist
   * item.
   */
  const METHOD_BROKEN = 'broken';
  const METHOD_AUTO = 'auto';
  const METHOD_MANUAL = 'manual';
  const METHOD_INTERACTIVE = 'interactive';
  const METHOD_RECOVERED = 'recovered';

  /**
   * Get the name of this item.
   *
   * @return string
   *   The name of the item.
   */
  public function getName() : string;

  /**
   * Get the method of the method.
   *
   * @return string
   *   The method this item was completed with.
   */
  public function getMethod() : string;

  /**
   * Find out if this checklist item is applicable.
   *
   * @return bool|null
   *   TRUE if it is definitely applicable
   *   FALSE if it definitely is NOT applicable
   *   NULL if we don't know yet.
   */
  public function isApplicable() : ?bool;

  /**
   * Find out if the checklist item is complete.
   *
   * @return bool
   *   TRUE if complete, FALSE otherwise.
   */
  public function isComplete() : bool;

  /**
   * Find out if the checklist item is incomplete.
   *
   * @return bool
   *   TRUE if incomplete, FALSE otherwise.
   */
  public function isIncomplete() : bool;

  /**
   * Set complete.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface
   *   The updated checklist item entity.
   */
  public function setComplete(string $type = self::METHOD_INTERACTIVE) : ChecklistItemInterface;

  /**
   * Set Incomplete.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface
   *   The updated checklist item entity.
   */
  public function setIncomplete() : ChecklistItemInterface;

  /**
   * Find out if the checklist item has failed.
   *
   * @return bool
   *   TRUE if failed, FALSE otherwise.
   */
  public function isFailed() : bool;

  /**
   * Set the checklist item as failed.
   *
   * @param string $type
   *   The method the item failed with.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface
   *   The updated checklist item entity.
   */
  public function setFailed(string $type = self::METHOD_INTERACTIVE) : ChecklistItemInterface;

  /**
   * Find out whether it has been attempted.
   *
   * @return bool
   *   TRUE if attempted, FALSE otherwise.
   */
  public function isAttempted() : bool;

  /**
   * Set the checklist item as attempted.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface
   *   The updated checklist item entity.
   */
  public function setAttempted() : ChecklistItemInterface;

  /**
   * Check whether the checklist item is required.
   *
   * @return bool
   *   TRUE if required, FALSE otherwise.
   */
  public function isRequired() : bool;

  /**
   * Check whether the checklist item is optional.
   *
   * @return bool
   *   TRUE if optional, FALSE otherwise.
   */
  public function isOptional() : bool;

  /**
   * Check whether this item can be actioned yet.
   *
   * @return bool
   *   TRUE if actionable, FALSE otherwise.
   */
  public function isActionable() : bool;

  /**
   * Do the item.
   *
   * This is usually only called with the auto method.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface
   *   The updated checklist item interface.
   */
  public function action() : ChecklistItemInterface;

  /**
   * Get the checklist item handler.
   *
   * @return \Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface
   *   The configured and instantiated handler for this item.
   */
  public function getHandler() : ChecklistItemHandlerInterface;

  /**
   * Add an outcome to this checklist item.
   *
   * @param string $name
   *   The name of the outcome.
   * @param mixed $value
   *   The value of the outcome.
   *
   * @return $this
   */
  public function setOutcome(string $name, $value) : ChecklistItemInterface;

}
