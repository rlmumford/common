<?php

namespace Drupal\field_tools\Plugin\EntityReferenceSelection;

use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Plugin\EntityReferenceSelection\UserSelection as CoreUserSelection;
use Drupal\user\RoleInterface;

class UserSelection extends CoreUserSelection {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $configuration = $this->getConfiguration();

    // Labels of the real (non-anonymous, non-authenticated) roles a new user
    // can be stored as. Replaces the deprecated user_role_names() (deprecated in
    // drupal:10.2.0, removed in drupal:11.0.0).
    $role_names = [];
    foreach (Role::loadMultiple() as $role) {
      if (!in_array($role->id(), [RoleInterface::ANONYMOUS_ID, RoleInterface::AUTHENTICATED_ID], TRUE)) {
        $role_names[$role->id()] = $role->label();
      }
    }

    $form['auto_create_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Store new users as'),
      '#options' => $role_names,
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
