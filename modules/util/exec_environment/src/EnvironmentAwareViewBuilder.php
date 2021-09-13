<?php

namespace Drupal\exec_environment;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\exec_environment\Event\EntityBuildEnvironmentDetectionEvent;
use Drupal\exec_environment\Event\ExecEnvironmentEvents;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Environment aware view builder.
 *
 * Makes sure the entities are rendered in the correct environment.
 */
class EnvironmentAwareViewBuilder extends EntityViewBuilder {

  /**
   * The event dispatcher.
   *
   * @var mixed|\Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return parent::createInstance($container, $entity_type)
      ->setEventDispatcher($container->get('event_dispatcher'));
  }

  /**
   * Set the event dispatcher service.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   *
   * @return $this
   */
  public function setEventDispatcher(EventDispatcherInterface $event_dispatcher) {
    $this->eventDispatcher = $event_dispatcher;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function buildMultiple(array $build_list) {
    $entities = [];
    foreach ($build_list as $build) {
      $entities[] = $build['#'.$this->entityTypeId];
    }
    $event = new EntityBuildEnvironmentDetectionEvent($this->entityType, $entities);
    $this->eventDispatcher->dispatch(ExecEnvironmentEvents::DETECT_ENTITY_BUILD_ENVIRONMENT . $this->entityTypeId, $event);
    $event->applyEnvironment();
    $return = parent::buildMultiple($build_list);
    $event->resetEnvironment();
    return $return;
  }

}
