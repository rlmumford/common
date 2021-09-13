<?php

namespace Drupal\exec_environment\Plugin\ExecEnvironment\Component;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
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
   * The user that was set.
   *
   * @var \Drupal\Core\Session\AccountInterface|bool
   */
  protected $setUser;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('current_user'),
      $container->get('module_handler')
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
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    EntityStorageInterface $user_storage,
    AccountProxyInterface $current_user,
    ModuleHandlerInterface $module_handler
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->userStorage = $user_storage;
    $this->currentUser = $current_user;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Check the access to switch to the target user.
   *
   * If we have found a user that we want to set as the current user, we
   * need to check whether the user applying the environment is allowed to
   * switch to this user. By default this access is granted if:
   * - The current user has the 'enter any user environment' permission
   * - OR The current user has the 'entity {role} user permission' for every
   * role the target user has.
   *
   * @param \Drupal\Core\Session\AccountInterface $target_user
   *   The target user.
   *
   * @return bool
   *   TRUE if the target user can be switched to, FALSE otherwise.
   */
  protected function checkAccess(AccountInterface $target_user) : bool {
    // We don't check access if the target user is the same as the current
    // user.
    if ($target_user->id() == $this->currentUser->id()) {
      return TRUE;
    }

    $access = AccessResult::allowedIfHasPermission($this->currentUser, 'enter any user environment');
    $roles_access = AccessResult::allowed();
    foreach ($target_user->getRoles() as $role) {
      $roles_access->andIf(
        AccessResult::allowedIfHasPermission($this->currentUser, 'enter ' . $role . ' user environment')
      );
    }
    $access->orIf($roles_access);

    // Allow modules to base access on different things.
    $results = $this->moduleHandler->invokeAll(
      'exec_environment_set_current_user_access',
      [$target_user, $this->currentUser]
    );
    foreach (array_filter($results) as $result_access) {
      $access->orIf($result_access);
    }

    return $access->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetCurrentUser(): ?AccountInterface {
    if (is_null($this->setUser)) {
      $this->setUser = FALSE;
      if ($this->configuration['user'] instanceof AccountInterface) {
        $user = $this->configuration['user'];
      }
      elseif (is_numeric($this->configuration['user'])) {
        $user = $this->userStorage->load($this->configuration['user']);
      }

      if (isset($user) && $this->checkAccess($user)) {
        $this->setUser = $user;
      }
    }

    return $this->setUser ?: NULL;
  }

}
