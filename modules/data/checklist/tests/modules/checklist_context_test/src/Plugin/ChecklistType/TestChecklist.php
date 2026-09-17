<?php

namespace Drupal\checklist_context_test\Plugin\ChecklistType;

use Drupal\checklist\Plugin\ChecklistType\ChecklistTypeBase;

/**
 * A checklist on a user, independent of Task and Service.
 *
 * @ChecklistType(
 *   id = "context_test",
 *   label = @Translation("Context test"),
 *   entity_type = "user"
 * )
 */
class TestChecklist extends ChecklistTypeBase {}
