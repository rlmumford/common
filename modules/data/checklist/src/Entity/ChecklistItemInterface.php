<?php

namespace Drupal\checklist\Entity;

use Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 *
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
   */
  public function getName() : string;

  /**
   * Get the method of the method.
   *
   * @return string
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
   */
  public function isComplete() : bool;

  /**
   * Find out if the checklist item is incomplete.
   *
   * @return bool
   */
  public function isIncomplete() : bool;

  /**
   * Set complete.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface
   */
  public function setComplete(string $type = self::METHOD_INTERACTIVE) : ChecklistItemInterface;

  /**
   * Set Incomplete.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface
   */
  public function setIncomplete() : ChecklistItemInterface;

  /**
   * Find out if the checklist item has failed.
   *
   * @return bool
   */
  public function isFailed() : bool;

  /**
   * Set the checklist item as failed.
   *
   * @param string $type
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface
   */
  public function setFailed(string $type = self::METHOD_INTERACTIVE) : ChecklistItemInterface;

  /**
   * Find out whether it has been attempted.
   *
   * @return bool
   */
  public function isAttempted() : bool;

  /**
   * Set the checklist item as attempted.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface
   */
  public function setAttempted() : ChecklistItemInterface;

  /**
   * Check whether the checklist item is required.
   *
   * @return bool
   */
  public function isRequired() : bool;

  /**
   * Check whether the checklist item is optional.
   *
   * @return bool
   */
  public function isOptional() : bool;

  /**
   * Check whether this item can be actioned yet.
   *
   * @return bool
   */
  public function isActionable() : bool;

  /**
   * Do the item.
   *
   * This is usually only called with the auto method.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface
   */
  public function action() : ChecklistItemInterface;

  /**
   * Get the checklist item handler.
   *
   * @return \Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface
   */
  public function getHandler() : ChecklistItemHandlerInterface;

}
