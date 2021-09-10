<?php


namespace Drupal\exec_environment\EventSubscriber;

use Drupal\Core\Session\AccountInterface;
use Drupal\exec_environment\EnvironmentComponentManager;
use Drupal\exec_environment\Plugin\ExecEnvironment\Component\ComponentInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Base class for services that want to subscribe to environment detection events.
 */
abstract class DetectEnvironmentSubscriberBase implements EventSubscriberInterface {

  /**
   * The component manager.
   *
   * @var \Drupal\exec_environment\EnvironmentComponentManager
   */
  protected $componentManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * DetectEnvironmentSubscriberBase constructor.
   *
   * @param \Drupal\exec_environment\EnvironmentComponentManager $component_manager
   *   The component manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(EnvironmentComponentManager $component_manager, AccountInterface $current_user) {
    $this->componentManager = $component_manager;
    $this->currentUser = $current_user;
  }

  /**
   * Create a new component.
   *
   * @param string $plugin_id
   *   The plugin id.
   * @param array $config
   *   The configuration.
   *
   * @return \Drupal\exec_environment\Plugin\ExecEnvironment\Component\ComponentInterface
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function createComponent(string $plugin_id, array $config = []) : ComponentInterface {
    return $this->componentManager->createInstance($plugin_id, $config);
  }

}
