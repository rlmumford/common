<?php

namespace Drupal\identity_ui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class IdentityDataList
 *
 * @Block(
 *   id = "identity_data_list",
 *   deriver = "\Drupal\identity_ui\Plugin\Derivative\IdentityDataClassDeriver",
 *   context = {
 *     "identity" = @ContextDefinition("entity:identity", label = @Translation("Identity")),
 *   }
 * );
 *
 *
 * @package Drupal\identity_ui\Plugin\Block
 */
class IdentityDataList extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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

  /**
   * IdentityDataList constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Get the identity data class.
   *
   * @return string
   */
  protected function getIdentityDataClass() {
    return $this->pluginDefinition['identity_data_class'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\identity\Entity\Identity $identity */
    $identity = $this->getContextValue('identity');

    $datas = $identity->getData($this->getIdentityDataClass());

    // @todo: Optional edit links
    // @todo: Optional "add" link
    // @todo: Filter options.

    return $this->entityTypeManager
      ->getViewBuilder('identity_data')
      ->viewMultiple($datas);
  }
}
