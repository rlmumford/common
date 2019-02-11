<?php

namespace Drupal\task\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

class TaskPlanForm extends EntityForm {

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

    $form['code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Code'),
      '#default_value' => $entity->get('code'),
      '#size' => 8,
      '#maxlength' => 8,
      '#required' => TRUE,
    ];

    $bundle_info = \Drupal::service('entity_type.bundle.info')->getBundleInfo('task');
    if (count($bundle_info) === 1) {
      $form['bundle'] = [
        '#type' => 'value',
        '#value' => key($bundle_info),
      ];
    }
    else {
      $bundle_options = array();
      foreach ($bundle_info as $key => $info) {
        $bundle_options[$key] = $info['label'];
      }

      $form['bundle'] = [
        '#type' => 'select',
        '#title' => $this->t('Task Bundle'),
        '#default_value' => $entity->get('bundle'),
        '#required' => TRUE,
        '#options' => $bundle_options,
      ];
    }

    $description = $entity->get('description');
    $form['description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Description'),
      '#default_value' => $description['value'],
      '#format' => $description['format'] ?: 'basic_html',
      '#required' => TRUE,
    ];

    $instructions = $entity->get('instructions');
    $form['instructions'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Instructions'),
      '#default_value' => $instructions['value'],
      '#format' => $instructions['format'] ?: 'basic_html',
      '#required' => TRUE,
    ];

    $form['default_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Title'),
      '#default_value' => $entity->get('default_title'),
      '#required' => TRUE,
    ];

    return parent::form($form, $form_state);
  }
}
