<?php

namespace Drupal\user_role_labels\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\RoleStorageInterface;
use Drupal\user\UserStorageInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class UserRoleLabel
 *
 * @ViewsField("user_role_label")
 *
 * @package Drupal\user_role_labels\Plugin\views\field
 */
class UserRoleLabel extends FieldPluginBase {

  /**
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * @var \Drupal\user\RoleStorageInterface
   */
  protected $roleStorage;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('entity_type.manager')->getStorage('user_role')
    );
  }

  public function __construct(array $configuration, $plugin_id, $plugin_definition, UserStorageInterface $user_storage, RoleStorageInterface $role_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->userStorage = $user_storage;
    $this->roleStorage = $role_storage;
  }

  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['role_context'] = ['default' => ''];
    return $options;
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $role_context_options = [];
    foreach ($this->roleStorage->loadMultiple() as $role) {
      if ($role->getThirdPartySetting('user_role_labels', 'label_enabled', FALSE)) {
        $role_context_options['role:' . $role->id()] = $role->label();
      }
    }

    $form['role_context'] = [
      '#type' => 'select',
      '#title' => new TranslatableMarkup('Role'),
      '#options' => $role_context_options,
      '#default_value' => $this->options['role_context'],
      '#empty_option' => new TranslatableMarkup('None'),
      '#empty_value' => 'none',
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->additional_fields['uid'] = 'uid';
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $this->addAdditionalFields();
  }

  public function render(ResultRow $values) {
    $uid = $this->getValue($values, 'uid');

    /** @var \Drupal\user\UserInterface $user */
    $user = $this->userStorage->load($uid);

    if (!empty($this->options['role_context']) && ($this->options['role_context'] != 'none')) {
      list($type, $key) = explode(':', $this->options['role_context'], 2);
      if ($type == 'role') {
        $field_name = "role_label_{$key}";

        return $user->get($field_name)->isEmpty() ? "< blank >" : $user->get($field_name)->value;
      }
      else if ($type == 'filter') {
        // @todo: Implement retrieving the role_context from a filter.
        return $user->getDisplayName();
      }
    }

    return $user->getDisplayName();
  }

}
