<?php

namespace Drupal\exec_environment;

use Drupal\exec_environment\Event\EnvironmentDetectionEvent;
use Drupal\exec_environment\Event\ExecEnvironmentEvents;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The environment stack service.
 *
 * @package Drupal\exec_environment
 */
class EnvironmentStack implements EnvironmentStackInterface {
  use ContainerAwareTrait;

  /**
   * The environment stack.
   *
   * @var \Drupal\exec_environment\EnvironmentInterface[]
   */
  protected $stack = [];

  /**
   * The default environment.
   *
   * @var \Drupal\exec_environment\Environment
   */
  protected $defaultEnvironment;

  /**
   * The impact applicator manager.
   *
   * @var \Drupal\exec_environment\EnvironmentImpactApplicatorManager
   */
  protected $impactApplicatorManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * EnvironmentStack constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(
    EventDispatcherInterface $event_dispatcher
  ) {
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function applyEnvironment(EnvironmentInterface $environment) {
    $current_environment = end($this->stack) ?: $this->defaultEnvironment();
    $environment->setPreviousEnvironment($current_environment);
    array_push($this->stack, $environment);

    foreach ($this->impactApplicatorManager()->getDefinitions() as $id => $definition) {
      /** @var \Drupal\exec_environment\Plugin\ExecEnvironment\ImpactApplicator\ImpactApplicatorInterface $applicator */
      $applicator = $this->impactApplicatorManager()->createInstance($id);
      $applicator->apply($environment);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function resetEnvironment() {
    $current_environment = array_pop($this->stack);
    foreach ($this->impactApplicatorManager()->getDefinitions() as $id => $definition) {
      /** @var \Drupal\exec_environment\Plugin\ExecEnvironment\ImpactApplicator\ImpactApplicatorInterface $applicator */
      $applicator = $this->impactApplicatorManager()->createInstance($id);
      $applicator->reset($current_environment);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveEnvironment(): EnvironmentInterface {
    return end($this->stack) ?: $this->defaultEnvironment();
  }

  /**
   * Construct the default environment.
   *
   * @return \Drupal\exec_environment\EnvironmentInterface
   *   The default environment.
   */
  protected function defaultEnvironment() : EnvironmentInterface {
    if (!$this->defaultEnvironment) {
      $event = new EnvironmentDetectionEvent();
      $this->eventDispatcher->dispatch(ExecEnvironmentEvents::DETECT_DEFAULT_ENVIRONMENT, $event);
      $this->defaultEnvironment = $event->getEnvironment();
    }
    return $this->defaultEnvironment;
  }

  /**
   * {@inheritdoc}
   */
  public function resetDefaultEnvironment() : EnvironmentStackInterface {
    $this->defaultEnvironment = NULL;
    return $this;
  }

  /**
   * Get the impact applicator manager.
   *
   * @return \Drupal\exec_environment\EnvironmentImpactApplicatorManager
   *   The impact applicator.
   */
  protected function impactApplicatorManager() : EnvironmentImpactApplicatorManager {
    if (!$this->impactApplicatorManager) {
      $this->impactApplicatorManager = $this->container->get('plugin.manager.exec_environment_impact_applicator');
    }
    return $this->impactApplicatorManager;
  }

}
