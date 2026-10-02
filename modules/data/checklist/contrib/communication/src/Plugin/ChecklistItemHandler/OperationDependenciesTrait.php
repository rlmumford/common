<?php

namespace Drupal\checklist_communication\Plugin\ChecklistItemHandler;

use Drupal\communication\OperationPluginManager;
use Drupal\communication\OperationVariantPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tracks the providers of configured communication operations and variants.
 */
trait OperationDependenciesTrait {

  /**
   * The operation plugin manager.
   */
  protected OperationPluginManager $operations;

  /**
   * The operation variant manager.
   */
  protected OperationVariantPluginManager $variants;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->operations = $container->get('plugin.manager.communication.operation');
    $instance->variants = $container->get('plugin.manager.communication.operation_variant');
    return $instance;
  }

  /**
   * Returns module dependencies for one configured operation.
   */
  protected function operationModules(string $operation, string $variant): array {
    $modules = ['checklist_communication', $this->operations->getDefinition($operation)['provider']];
    if ($variant !== '') {
      $definition = $this->variants->getDefinitionsForOperation($operation)[$variant] ?? NULL;
      if (!$definition) {
        throw new \InvalidArgumentException('Select a compatible variant for this operation.');
      }
      $modules[] = $definition['provider'];
    }
    return $modules;
  }

}
