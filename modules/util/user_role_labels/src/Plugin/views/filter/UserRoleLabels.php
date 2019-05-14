<?php

namespace Drupal\user_role_labels\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\RoleStorageInterface;
use Drupal\views\Plugin\views\filter\StringFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class UserRoleLabels
 *
 * @ViewsFilter("user_name_role_labels_filter")
 *
 * @package Drupal\user_role_labels\Plugin\views\filter
 */
class UserRoleLabels extends StringFilter {

  /**
   * @var \Drupal\user\RoleStorageInterface
   */
  protected $roleStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('entity_type.manager')->getStorage('user_role')
    );
  }

  /**
   * UserRoleLabels constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\Database\Connection $connection
   * @param \Drupal\user\RoleStorageInterface $role_storage
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $connection, RoleStorageInterface $role_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $connection);
    $this->roleStorage = $role_storage;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['roles'] = [
      'default' => [],
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $role_options = [];
    foreach ($this->roleStorage->loadMultiple() as $role) {
      if ($role->getThirdPartySetting('user_role_labels', 'label_enabled', FALSE)) {
        $role_options[$role->id()] = $role->label();
      }
    }

    $form['roles'] = [
      '#type' => 'checkboxes',
      '#options' => $role_options,
      '#default_value' => $this->options['roles'],
      '#title' => new TranslatableMarkup('Include the following Role Labels'),
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * Get the roles that have a label field.
   *
   * @return \Drupal\user\RoleInterface[]
   */
  protected function getRolesWithLabelFields() {
    $rids = $this->options['roles'];
    $return = [];
    foreach ($this->roleStorage->loadMultiple($rids) as $rid => $role) {
      /** @var $role \Drupal\user\RoleInterface */
      if ($role->getThirdPartySetting('user_role_labels', 'label_enabled', FALSE)) {
        $return[$rid] = $role;
      }
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function operators() {
    $operators = parent::operators();

    $not_supported = [
      'word',
      'allwords',
      'starts',
      'not_starts',
      'ends',
      'not_ends',
      'shorterthan',
      'longerthan',
      'regular_expression',
    ];
    foreach ($not_supported as $unsupported_op) {
      unset($operators[$unsupported_op]);
    }

    return $operators;
  }

  /**
   * {@inheritdoc}
   */
  public function opEqual($field) {
    $roles = $this->getRolesWithLabelFields();

    if (empty($roles)) {
      parent::opEqual($field);
    }
    else {
      $or = new Condition('OR');
      $or->condition($field, $this->value, $this->operator());

      foreach ($roles as $id => $role) {
        $field_name = "role_label_" . $id;
        $or->condition("{$this->tableAlias}.{$field_name}", $this->value, $this->operator());
      }

      $this->query->addWhere($this->options['group'], $or);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function opContains($field) {
    $roles = $this->getRolesWithLabelFields();

    if (empty($roles)) {
      parent::opContains($field);
    }
    else {
      $or = new Condition('OR');
      $or->condition($field, '%'.$this->connection->escapeLike($this->value).'%', 'LIKE');

      foreach ($roles as $id => $role) {
        $field_name = "role_label_" . $id;
        $or->condition("{$this->tableAlias}.{$field_name}", '%'.$this->connection->escapeLike($this->value).'%', 'LIKE');
      }

      $this->query->addWhere($this->options['group'], $or);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function opNotLike($field) {$roles = $this->getRolesWithLabelFields();

    if (empty($roles)) {
      parent::opNotLike($field);
    }
    else {
      $and = new Condition('AND');
      $and->condition($field, '%'.$this->connection->escapeLike($this->value).'%', 'NOT LIKE');

      foreach ($roles as $id => $role) {
        $field_name = "role_label_" . $id;
        $and->condition("{$this->tableAlias}.{$field_name}", '%'.$this->connection->escapeLike($this->value).'%', 'NOT LIKE');
      }

      $this->query->addWhere($this->options['group'], $and);
    }
  }

}
