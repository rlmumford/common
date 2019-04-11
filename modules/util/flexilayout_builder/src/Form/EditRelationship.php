<?php

namespace Drupal\flexilayout_builder\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\flexilayout_builder\Plugin\SectionStorage\DisplayWideConfigSectionStorageInterface;
use Drupal\layout_builder\DefaultsSectionStorageInterface;

class EditRelationship extends AddRelationship {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, DefaultsSectionStorageInterface $section_storage = NULL, $machine_name = NULL) {
    return $this->doBuildForm($form, $form_state, $section_storage, 'edit', $machine_name);
  }

}
