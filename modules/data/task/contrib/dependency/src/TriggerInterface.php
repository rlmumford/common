<?php

namespace Drupal\task_dependency;

use Drupal\Core\Entity\EntityInterface;

/**
 * Pure matching contract shared by event subscriptions and job adapters.
 */
interface TriggerInterface {

  /**
   * Names the entity context to bind for this trigger.
   */
  public function contextName(): string;

  /**
   * Validates the target and configuration, throwing on unsupported input.
   */
  public function validateTarget(EntityInterface $target): void;

  /**
   * Matches a transition at save time, never against delayed worker state.
   */
  public function matches(EntityInterface $entity, ?EntityInterface $original): bool;

}
