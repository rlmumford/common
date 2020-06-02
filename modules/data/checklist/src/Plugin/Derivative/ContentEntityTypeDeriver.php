<?php

namespace Drupal\checklist\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ContentEntityTypeDeriver extends DeriverBase implements ContainerDeriverInterface {
  use StringTranslationTrait;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('entity_type.manager')
    );
  }

  /**
   * ContentEntityTypeDeriver constructor.
   *
   * @param string $base_plugin_id
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(string $base_plugin_id, EntityTypeManagerInterface $entity_type_manager) {
    $this->basePluginId = $base_plugin_id;
    $this->entityTypeManager = $entity_type_manager;
  }

  public function getDerivativeDefinitions($base_plugin_definition) {
    if (empty($this->derivatives)) {
      foreach ($this->entityTypeManager->getDefinitions() as $id => $entity_type) {
        if ($entity_type instanceof ContentEntityType) {
          $this->derivatives[$id] = [
            'entity_type' => $id,
            'label' => $this->t('Create @entity', ['@entity' => $entity_type->getLabel()]),
          ] + $base_plugin_definition;
        }
      }
    }

    return $this->derivatives;
  }
}
