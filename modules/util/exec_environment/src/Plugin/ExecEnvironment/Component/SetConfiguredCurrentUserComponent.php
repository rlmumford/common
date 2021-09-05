<?php

namespace Drupal\exec_environment\Plugin\ExecEnvironment\Component;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Component that sets the current user from plugin configuration.
 *
 * @ExecEnvironmentComponent(
 *   id = "configured_current_user",
 * )
 *
 * @package Drupal\exec_environment\Plugin\ExecEnvironment\Component
 */
class SetConfiguredCurrentUserComponent extends ComponentBase implements CurrentUserComponentInterface, ContainerFactoryPluginInterface {

  /**
   * The user storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $userStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('user')
    );
  }

  /**
   * SetConfiguredCurrentUserComponent constructor.
   *
   * @param array $configuration
   *   The config.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $user_storage
   *   The user storage.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, EntityStorageInterface $user_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->userStorage = $user_storage;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentUser(): ?AccountInterface {
    if ($this->configuration['user'] instanceof AccountInterface) {
      return $this->configuration['user'];
    }
    else if (is_numeric($this->configuration['user'])) {
      return $this->userStorage->load($this->configuration['user']);
    }

    return NULL;
  }
}
