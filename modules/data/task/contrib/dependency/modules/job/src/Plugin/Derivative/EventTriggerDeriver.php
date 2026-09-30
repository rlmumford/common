<?php

namespace Drupal\task_dependency_job\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Supplies the same event identities to the job creation consumer.
 */
class EventTriggerDeriver extends DeriverBase implements ContainerDeriverInterface {
  use StringTranslationTrait;

  /**
   * Constructs the deriver.
   */
  public function __construct(protected EntityTypeManagerInterface $entities) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->entities->getDefinitions() as $type => $definition) {
      if (!$definition->entityClassImplements('Drupal\Core\Entity\FieldableEntityInterface')) {
        continue;
      }
      $this->derivatives['entity.state.' . $type] = [
        'label' => $this->t('@type enters a state', ['@type' => $definition->getLabel()]),
        'event' => 'entity.state',
        'context_definitions' => [
          'entity' => EntityContextDefinition::create($type),
          'original' => EntityContextDefinition::create($type),
        ],
      ] + $base_plugin_definition;
    }
    $this->derivatives['task.resolved'] = [
      'label' => $this->t('Task resolves'),
      'event' => 'task.resolved',
      'context_definitions' => [
        'entity' => EntityContextDefinition::create('task'),
        'original' => EntityContextDefinition::create('task'),
      ],
    ] + $base_plugin_definition;
    return $this->derivatives;
  }

}
