<?php

namespace Drupal\checklist\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver that makes a version of a plugin for each content entity type.
 */
class ContentEntityTypeDeriver extends DeriverBase implements ContainerDeriverInterface {
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The base plugin id.
   *
   * @var string
   */
  protected $basePluginId;

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
   *   The base plugin id.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(string $base_plugin_id, EntityTypeManagerInterface $entity_type_manager) {
    $this->basePluginId = $base_plugin_id;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
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
