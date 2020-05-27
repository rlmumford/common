<?php

namespace Drupal\project\Form;

use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for project type forms.
 */
class ProjectTypeForm extends BundleEntityFormBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $type = $this->entity;

    if ($this->operation == 'add') {
      $form['#title'] = $this->t('Add project type');
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
        'exists' => '\Drupal\project\Entity\ProjectType::load',
        'source' => ['label'],
      ],
      '#description' => t('A unique machine-readable name for this project type. It must only contain lowercase letters, numbers, and underscores.'),
    ];

    $form['settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Settings'),
    ];
    $form['settings']['default_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Path'),
      '#description' => $this->t('Where should links to this project type go. You may use tokens.'),
      '#default_value' => $type->get('default_uri') ? : 'project/[project:id]',
    ];
    $form['settings']['default_label'] = [
      '#type' => 'textfield',
      '#title' => t('Default Label'),
      '#description' => t('The default label for projects of this type. Each time a project is saved the label we be updated according to what is specified here unless this is left blank. You may use tokens.'),
      '#default_value' => $type->get('default_label') ? : '',
    ];

    return $this->protectBundleIdElement($form);
  }
}

