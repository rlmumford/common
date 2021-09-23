<?php

namespace Drupal\exec_environment\EventSubscriber;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\exec_environment\EnvironmentStackInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscriber to clear the base environment just after authentication.
 */
class AuthenticationSubscriber implements EventSubscriberInterface {

  /**
   * The authentication provider.
   *
   * @var \Drupal\Core\Authentication\AuthenticationProviderInterface
   */
  protected $authenticationProvider;

  /**
   * The environment stack.
   *
   * @var \Drupal\exec_environment\EnvironmentStackInterface
   */
  protected $environmentStack;

  /**
   * Constructs an authentication subscriber.
   *
   * @param \Drupal\Core\Authentication\AuthenticationProviderInterface $authentication_provider
   *   An authentication provider.
   * @param \Drupal\exec_environment\EnvironmentStackInterface $environment_stack
   *   The environment stack.
   */
  public function __construct(AuthenticationProviderInterface $authentication_provider, EnvironmentStackInterface $environment_stack) {
    $this->authenticationProvider = $authentication_provider;
    $this->environmentStack = $environment_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // The priority for authentication must be 1 lower than the event defined in
    // \Drupal\Core\EventSubscriber\AuthenticationSubscriber
    $events[KernelEvents::REQUEST][] = ['onKernelRequestAuthenticate', 299];
    return $events;
  }

  /**
   * Clears the execution environment just after the core authentication event.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The request event.
   *
   * @see \Drupal\Core\Authentication\AuthenticationProviderInterface::authenticate()
   */
  public function onKernelRequestAuthenticate(GetResponseEvent $event) {
    if ($event->isMasterRequest()) {
      $request = $event->getRequest();
      if ($this->authenticationProvider->applies($request)) {
        $this->environmentStack->resetDefaultEnvironment();
      }
    }
  }

}
