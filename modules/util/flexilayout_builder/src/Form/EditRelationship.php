<?php

namespace Drupal\flexilayout_builder\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\SectionStorageInterface;

class EditRelationship extends AddRelationship {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, SectionStorageInterface $section_storage = NULL, $machine_name = NULL) {
    return $this->doBuildForm($form, $form_state, $section_storage, 'edit', $machine_name);
  }

}
