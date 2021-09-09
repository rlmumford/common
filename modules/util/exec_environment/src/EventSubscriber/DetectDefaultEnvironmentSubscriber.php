<?php

namespace Drupal\exec_environment\EventSubscriber;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\exec_environment\EnvironmentComponentManager;
use Drupal\exec_environment\Event\EnvironmentDetectionEvent;
use Drupal\exec_environment\Event\ExecEnvironmentEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to detect the default environment.
 *
 * @package Drupal\exec_environment\EventSubscriber
 */
class DetectDefaultEnvironmentSubscriber implements EventSubscriberInterface {

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
   * DetectDefaultEnvironmentSubscriber constructor.
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
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ExecEnvironmentEvents::DETECT_DEFAULT_ENVIRONMENT] = 'onDetectDefaultEnvironment';
    return $events;
  }

  /**
   * Add a configured current user component to the default environment.
   *
   * @param \Drupal\exec_environment\Event\EnvironmentDetectionEvent $event
   *   The detection event.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function onDetectDefaultEnvironment(EnvironmentDetectionEvent $event) {
    $event->getEnvironment()->addComponent($this->componentManager->createInstance(
      'configured_current_user',
      ['user' => $this->currentUser instanceof AccountProxyInterface ? $this->currentUser->getAccount() : $this->currentUser]
    ));
  }
}
