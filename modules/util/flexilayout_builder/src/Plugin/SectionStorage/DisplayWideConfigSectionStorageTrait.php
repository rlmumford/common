<?php

namespace Drupal\flexilayout_builder\Plugin\SectionStorage;

use Drupal\layout_builder\SectionStorage\SectionStorageDefinition;
use Symfony\Component\Routing\RouteCollection;

trait DisplayWideConfigSectionStorageTrait {

  /**
   * Configuration
   *
   * @var array
   */
  protected $config = [];

  /**
   * @param string $key
   *
   * @return array
   */
  public function getConfig($key = '') {
    return $key ? (isset($this->config[$key]) ? $this->config[$key] : [])  : $this->config;
  }

  /**
   * @param array $config
   */
  public function setConfig($key, $config) {
    $this->config[$key] = $config;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function buildLayoutRoutes(RouteCollection $collection, SectionStorageDefinition $definition, $path, array $defaults = [], array $requirements = [], array $options = [], $route_name_prefix = '') {
    parent::buildLayoutRoutes($collection, $definition, $path, $defaults, $requirements, $options, $route_name_prefix);

    $type = $definition->id();
    if ($route_name_prefix) {
      $route_name_prefix = "layout_builder.$type.$route_name_prefix";
    }
    else {
      $route_name_prefix = "layout_builder.$type";
    }

    $route = $collection->get($route_name_prefix.'.view');
    $route->setDefault('_controller', '\Drupal\flexilayout_builder\Controller\FlexiLayoutBuilderController::layout');
  }

  /**
   * {@inheritdoc}
   */
  public function getContexts() {
    /** @var \Drupal\Core\Plugin\Context\ContextInterface[] $contexts */
    $contexts = parent::getContexts();

    $static_contexts = $this->getConfig('static_context');
    $contexts += \Drupal::service('ctools.context_mapper')->getContextValues($static_contexts ?: []);

    $relationship_manager = \Drupal::service('plugin.manager.ctools.relationship');
    $context_handler = \Drupal::service('context.handler');
    if ($relationships = $this->getConfig('relationships')) {
      foreach ($relationships as $machine_name => $relationship) {
        /** @var \Drupal\ctools\Plugin\RelationshipInterface $plugin */
        $plugin = $relationship_manager->createInstance($relationship['plugin'], $relationship['settings'] ?: []);
        $context_handler->applyContextMapping($plugin, $contexts);

        $contexts[$machine_name] = $plugin->getRelationship();
      }
    }

    return $contexts;
  }
}
