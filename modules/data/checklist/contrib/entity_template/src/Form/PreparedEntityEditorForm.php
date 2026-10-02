<?php

namespace Drupal\checklist_entity_template\Form;

use Drupal\checklist_flexiform\Form\ChecklistForm;

/**
 * Uses the shared checklist form host for a prepared entity editor.
 */
class PreparedEntityEditorForm extends ChecklistForm {

  /**
   * {@inheritdoc}
   */
  protected function completionMessage() {
    return $this->t('Entity saved.');
  }

}
