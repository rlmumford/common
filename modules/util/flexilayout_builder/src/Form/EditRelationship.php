<?php
/**
 * Created by PhpStorm.
 * User: Mumford
 * Date: 13/10/2018
 * Time: 09:58
 */

namespace Drupal\flexilayout_builder\Form;


use Drupal\Core\Form\FormStateInterface;
use Drupal\flexilayout_builder\Plugin\SectionStorage\DisplayWideConfigSectionStorageInterface;

class EditRelationship extends AddRelationship {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, DisplayWideConfigSectionStorageInterface $section_storage = NULL, $machine_name = NULL) {
    return $this->doBuildForm($form, $form_state, $section_storage, 'edit', $machine_name);
  }

}
