<?php

namespace Drupal\flexilayout_builder\Plugin\SectionStorage;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\layout_builder\SectionStorage\SectionStorageDefinition;
use Symfony\Component\Routing\RouteCollection;

trait DisplayWideConfigSectionStorageTrait {

  /**
   * The sample entity generator.
   *
   * @var \Drupal\layout_builder\Entity\LayoutBuilderSampleEntityGenerator
   */
  protected $sampleEntityGenerator;

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

        /** @var \Drupal\Core\Plugin\Context\ContextInterface $context */
        $context = $plugin->getRelationship();

        if (!$context->hasContextValue() &&  $context->getContextDefinition() instanceof EntityContextDefinition) {
          $entity_type = substr($context->getContextDefinition()->getDataType(), 7);
          $bundles = $context->getContextDefinition()->getConstraint('Bundle') ?: [];

          if ($bundles) {
            $bundle = reset($bundles);
          }
          else {
            $bundle_info = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type);
            $bundle = key($bundle_info);
          }

          $context = Context::createFromContext($context, $this->sampleEntityGenerator->get($entity_type, $bundle));
        }

        $contexts[$machine_name] = $context;
      }
    }

    return $contexts;
  }
}
