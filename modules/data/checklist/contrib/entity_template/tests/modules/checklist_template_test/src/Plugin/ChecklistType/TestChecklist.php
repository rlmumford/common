<?php

namespace Drupal\checklist_template_test\Plugin\ChecklistType;

use Drupal\checklist\Plugin\ChecklistType\ChecklistTypeBase;

/**
 * A non-revisionable checklist host on both Drupal 10 and Drupal 11.
 *
 * @ChecklistType(
 *   id = "template_test",
 *   label = @Translation("Template test"),
 *   entity_type = "entity_test"
 * )
 */
class TestChecklist extends ChecklistTypeBase {}
