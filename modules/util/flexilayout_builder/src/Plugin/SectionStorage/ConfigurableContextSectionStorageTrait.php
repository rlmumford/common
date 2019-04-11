<?php

namespace Drupal\flexilayout_builder\Plugin\SectionStorage;

trait ConfigurableContextSectionStorageTrait {

  /**
   * Gets contexts for use during preview.
   *
   * When not in preview, ::getContexts() will be used.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   The plugin contexts suitable for previewing.
   */
  public function getContextsDuringPreview() {
    $contexts = parent::getContextsDuringPreview();
    $contexts += \Drupal::service('ctools.context_mapper')->getContextValues(
      $this->getStaticContextConfiguration()
    );

    /** @var \Drupal\ctools\Plugin\RelationshipManager $relationship_manager */
    $relationship_manager = \Drupal::service('plugin.manager.ctools.relationship');
    /** @var \Drupal\Core\Plugin\Context\ContextHandler $context_handler */
    $context_handler = \Drupal::service('context.handler');

    foreach ($this->getRelationshipsConfiguration() as $machine_name => $relationship) {
      /** @var \Drupal\ctools\Plugin\RelationshipInterface $plugin */
      $plugin = $relationship_manager->createInstance($relationship['plugin'], $relationship['settings'] ?: []);
      $context_handler->applyContextMapping($plugin, $contexts);

      $contexts[$machine_name] = $plugin->getRelationship();
    }

    return $contexts;
  }

  /**
   * Get the relationships configuration.
   *
   * @return array
   */
  protected function getRelationshipsConfiguration() {
    return [];
  }

  /**
   * Get the relationships configuration.
   *
   * @return array
   */
  protected function getStaticContextConfiguration() {
    return [];
  }
}
