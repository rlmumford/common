<?php

namespace Drupal\review\Form;

use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for review type forms.
 */
class ReviewTypeForm extends BundleEntityFormBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $type = $this->entity;

    if ($this->operation == 'add') {
      $form['#title'] = $this->t('Add review type');
    }
    else {
      $form['#title'] = $this->t('Edit %label', ['%label' => $type->label()]);
    }

    $form['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#default_value' => $type->label(),
      '#description' => t('The human-readable name of this type.'),
      '#required' => TRUE,
      '#size' => 30,
    ];

    // Machine-readable type name.
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $type->id(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#machine_name' => [
        'exists' => '\Drupal\review\Entity\reviewType::load',
        'source' => ['label'],
      ],
      '#description' => t('A unique machine-readable name for this review type. It must only contain lowercase letters, numbers, and underscores.'),
    ];

    $form['target_entity_type_id'] = [
      '#type' => 'select',
    ];

    return $this->protectBundleIdElement($form);
  }
}

