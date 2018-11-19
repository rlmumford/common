<?php

namespace Drupal\task\Form;

use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Form\FormStateInterface;

class TaskBundleForm extends BundleEntityFormBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\task\Entity\TaskPlan $entity */
    $entity = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Task Plan Name'),
      '#default_value' => $entity->label,
      '#size' => 30,
      '#required' => TRUE,
      '#maxlength' => 64,
      '#description' => $this->t('The name of this task plan.'),
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity
        ->id(),
      '#required' => TRUE,
      '#disabled' => !$entity
        ->isNew(),
      '#size' => 30,
      '#maxlength' => 64,
      '#machine_name' => [
        'exists' => [
          '\\Drupal\\task\\Entity\\TaskPlan',
          'load',
        ],
      ],
    ];

    return parent::form($form, $form_state);
  }
}
