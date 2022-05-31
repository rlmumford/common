<?php

namespace Drupal\checklist;

/**
 * Interface for the checklist context collector service.
 */
interface ChecklistContextCollectorInterface {

  /**
   * Collect the configuration contexts.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The checklist to collect configuration contexts for.
   * @param array $config_context
   *   Contextual information about the configuration.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   An array of contexts.
   */
  public function collectConfigContexts(ChecklistInterface $checklist, array $config_context = []) : array;

  /**
   * Collect the runtime contexts.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The checklist to collect runtime contexts for.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   An array of contexts.
   */
  public function collectRuntimeContexts(ChecklistInterface $checklist) : array;

}
