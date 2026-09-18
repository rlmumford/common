<?php

namespace Drupal\checklist_resolver_test\Plugin\ChecklistType;

use Drupal\checklist\Plugin\ChecklistType\ChecklistTypeBase;

/**
 * Tests isolation from user checklists with matching IDs and field names.
 *
 * @ChecklistType(
 *   id = "entity_context_test",
 *   label = @Translation("Entity context test"),
 *   entity_type = "entity_test"
 * )
 */
class EntityTestChecklist extends ChecklistTypeBase {}
