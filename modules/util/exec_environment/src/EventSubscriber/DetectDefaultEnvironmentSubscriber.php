<?php

namespace Drupal\exec_environment\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\exec_environment\Event\EnvironmentDetectionEvent;
use Drupal\exec_environment\Event\ExecEnvironmentEvents;

/**
 * Event subscriber to detect the default environment.
 *
 * @package Drupal\exec_environment\EventSubscriber
 */
class DetectDefaultEnvironmentSubscriber extends DetectEnvironmentSubscriberBase {

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
    /** @var \Drupal\exec_environment\Plugin\ExecEnvironment\Component\CurrentUserComponentInterface $component */
    $component = $this->createComponent(
      'configured_current_user',
      ['user' => $this->currentUser instanceof AccountProxyInterface ? $this->currentUser->getAccount() : $this->currentUser]
    );
    // Call this method to ensure the set user gets cached against the component
    // to avoid being unable to switch back.
    $component->getTargetCurrentUser();

    $event->getEnvironment()->addComponent($component);
  }

}
