<?php

namespace Drupal\task_job;

use Drupal\Component\Plugin\LazyPluginCollection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_template\Entity\BlueprintEntityInterface;
use Drupal\task_job\Plugin\JobTrigger\JobTriggerInterface;
use Drupal\typed_data\Context\ContextDefinition;

interface JobInterface extends EntityInterface {

  /**
   * Get the default checklist items for this job.
   *
   * @return array
   *   An array of checklist item configuration keyed by the name.
   *   Each item should have atleast the following keys:
   *     - label - The label of the checklist item
   *     - handler - The handler plugin used for the checklist item.
   *     - handler_configuration - The configuration to be passed to the plugin.
   */
  public function getChecklistItems() : array;

  /**
   * Get the triggers associated with this job.
   *
   * @return array
   */
  public function getTriggersConfiguration() : array;

  /**
   * Get the trigger collection
   *
   * @return \Drupal\Component\Plugin\LazyPluginCollection
   */
  public function getTriggerCollection(): LazyPluginCollection;

  /**
   * Get a specific trigger.
   *
   * @param string $key
   *
   * @return \Drupal\task_job\Plugin\JobTrigger\JobTriggerInterface|null
   */
  public function getTrigger(string $key): ?JobTriggerInterface;

  /**
   * Check whether we have a trigger.
   *
   * @return bool
   */
  public function hasTrigger(string $key) : bool;

  /**
   * Get the context definitions for this job.
   *
   * @return \Drupal\Core\Plugin\Context\ContextDefinitionInterface[]
   *   The context definitions associated with this job.
   */
  public function getContextDefinitions();

  /**
   * Get the context definition.
   *
   * @param string $key
   *   The context key to get.
   *
   * @return \Drupal\typed_data\Context\ContextDefinition|null
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   */
  public function getContextDefinition(string $key);

  /**
   * Add a context definition.
   *
   * @param string $key
   *   The name of the context.
   * @param \Drupal\typed_data\Context\ContextDefinition $context_definition
   *   The definition of the context.
   */
  public function addContextDefinition(string $key, ContextDefinition $context_definition);

  /**
   * Remove a context definition.
   *
   * @param string $key
   *   The key of the context to remove.
   */
  public function removeContextDefinition(string $key);

}
