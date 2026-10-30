<?php

/**
 * @file
 * Hooks for integrations which supply mandatory checklist steps.
 */

/**
 * Alters reconciled checklist items, in their display and processing order.
 *
 * Runs after persisted items and current defaults have been reconciled. Added
 * items must have the same checklist host, key and type. Preserve persisted
 * identity and completed outcomes. Implementations must be idempotent because
 * job checklists reconcile again when a serialized workspace is restored.
 * Access/execution guards remain necessary; display ordering is not permission.
 *
 * @param \Drupal\checklist\Entity\ChecklistItemInterface[] $items
 *   Items keyed by their names, in processing order.
 * @param \Drupal\checklist\ChecklistInterface $checklist
 *   The checklist which owns these items.
 */
function hook_checklist_items_alter(array &$items, \Drupal\checklist\ChecklistInterface $checklist): void {
  // Integrations can prepend a prerequisite and constrain subsequent items.
}
