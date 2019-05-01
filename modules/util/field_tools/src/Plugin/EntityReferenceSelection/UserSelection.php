<?php

namespace Drupal\field_tools\Plugin\EntityReferenceSelection;

use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Plugin\EntityReferenceSelection\UserSelection as CoreUserSelection;
use Drupal\user\RoleInterface;

class UserSelection extends CoreUserSelection {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $configuration = $this->getConfiguration();

    $form['auto_create_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Store new users as'),
      '#options' => array_diff_key(user_role_names(TRUE), [RoleInterface::AUTHENTICATED_ID => RoleInterface::AUTHENTICATED_ID]),
      '#default_value' => !empty($configuration['auto_create_roles']) ? $configuration['auto_create_roles'] : [],
      '#states' => [
        'visible' => [
          ':input[name="settings[handler_settings][auto_create]"]' => ['checked' => TRUE],
        ],
      ],
      '#weight' => -1,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function createNewEntity($entity_type_id, $bundle, $label, $uid) {
    /** @var \Drupal\user\UserInterface $entity */
    $entity = parent::createNewEntity($entity_type_id, $bundle, $label, $uid);

    $auto_create_roles = $this->getConfiguration()['auto_create_roles'];
    foreach (array_filter($auto_create_roles) as $rid => $value) {
      $entity->addRole($rid);
    }

    return $entity;
  }
}
