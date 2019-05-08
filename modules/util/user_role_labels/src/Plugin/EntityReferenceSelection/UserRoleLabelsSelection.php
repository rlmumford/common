<?php

namespace Drupal\user_role_labels\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Plugin\EntityReferenceSelection\UserSelection;
use Drupal\user\RoleInterface;

/**
 * Provides specific access control for the user entity type.
 *
 * @EntityReferenceSelection(
 *   id = "user_role_labels",
 *   label = @Translation("User selection (with role labels)"),
 *   entity_types = {"user"},
 *   group = "default",
 *   weight = 1
 * )
 */
class UserRoleLabelsSelection extends UserSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = DefaultSelection::buildEntityQuery($match, $match_operator);

    $configuration = $this->getConfiguration();

    // Filter out the Anonymous user if the selection handler is configured to
    // exclude it.
    if (!$configuration['include_anonymous']) {
      $query->condition('uid', 0, '<>');
    }

    if (isset($match)) {
      $label_condition_group = $query->orConditionGroup();
      $label_condition_group->condition('name', $match, $match_operator);

      /** @var \Drupal\user\RoleInterface $role */
      foreach ($this->entityTypeManager->getStorage('user_role')->loadMultiple() as $role) {
        if (!$role->getThirdPartySetting('user_role_labels', 'label_enabled', FALSE)) {
          continue;
        }

        if (!empty($configuration['filter']['role']) && !in_array($role->id(), $configuration['filter']['role'])) {
          continue;
        }

        $label_condition_group->condition('role_label_'.$role->id(), $match, $match_operator);
      }
      $query->condition($label_condition_group);
    }

    // Filter by role.
    if (!empty($configuration['filter']['role'])) {
      $query->condition('roles', $configuration['filter']['role'], 'IN');
    }

    return $query;
  }

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
