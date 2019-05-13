<?php

namespace Drupal\mini_layouts\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MiniLayout
 *
 * @Block(
 *   id = "mini_layout",
 *   deriver = "Drupal\mini_layouts\Plugin\Deriver\MiniLayoutBlockDeriver",
 * )
 *
 * @package Drupal\mini_layouts\Plugin\Block
 */
class MiniLayout extends BlockBase implements ContextAwarePluginInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Builds and returns the renderable array for this block plugin.
   *
   * If a block should not be rendered because it has no content, then this
   * method must also ensure to return no content: it must then only return an
   * empty array, or an empty array with #cache set (with cacheability metadata
   * indicating the circumstances for it being empty).
   *
   * @return array
   *   A renderable array representing the content of the block.
   *
   * @see \Drupal\block\BlockViewBuilder
   */
  public function build() {
    /** @var \Drupal\mini_layouts\Entity\MiniLayout $mini_layout */
    $mini_layout = $this->entityTypeManager
      ->getStorage('mini_layout')
      ->load($this->getPluginDefinition()['mini_layout']);

    $build = [];
    foreach ($mini_layout->getSections() as $delta => $section) {
      $build[$delta] = $section->toRenderArray($this->getContexts());
    }

    return  $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }
}
