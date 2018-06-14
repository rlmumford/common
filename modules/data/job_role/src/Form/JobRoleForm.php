<?php

namespace Drupal\job_role\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for job_role forms.
 */
class JobRoleForm extends ContentEntityForm {

  /**
   * Form submission handler for the 'deactivate' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   A reference to a keyed array containing the current state of the form.
   */
  public function deactivate(array $form, FormStateInterface $form_state) {
    $form_state->setValue('status', FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    switch ($this->entity->save()) {
      case SAVED_NEW:
        drupal_set_message($this->t('%label has been created.', ['%label' => $this->entity->label()]));
        break;

      case SAVED_UPDATED:
        drupal_set_message($this->t('%label has been updated.', ['%label' => $this->entity->label()]));
        break;
    }
  }

}
