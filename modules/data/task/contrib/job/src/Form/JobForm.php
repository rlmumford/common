<?php

namespace Drupal\task_job\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\task_job\Entity\Job;

class JobForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['#tree'] = TRUE;

    $form['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#default_value' => $this->entity->label(),
      '#description' => t('The builder name.'),
      '#required' => TRUE,
      '#size' => 30,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#maxlength' => 128,
      '#machine_name' => [
        'exists' => Job::class.'::load',
        'source' => ['label'],
      ],
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#default_value' => $this->entity->get('description') ?: '',
      '#title' => $this->t('Description'),
      '#description' => $this->t('This is designed to help administrators understand what jobs are for. User facing documentation should be included in the instructions field or in the checklist.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    if ($this->entity->isNew()) {
      $actions['submit']['#value'] = $this->t('Create & Configure');
      $actions['submit']['#submit'][] = '::submitRedirectEdit';
    }

    return $actions;
  }

  /**
   * Redirect to the edit page.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function submitRedirectEdit(array $form, FormStateInterface $form_state) {
    $form_state->setRedirect('entity.task_job.edit_form', [
      'task_job' => $this->entity->id(),
    ]);
  }

}
