<?php

namespace Drupal\note\Plugin\Deriver;

use Consolidation\OutputFormatters\Transformations\StringTransformationInterface;
use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AttachedNoteFormBlockDeriver
 *
 * @package Drupal\note\Plugin\Deriver
 */
class AttachedNoteFormBlockDeriver extends DeriverBase implements ContainerDeriverInterface {
  use StringTranslationTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('string_translation')
    );
  }

  /**
   * AttachedNoteFormBlockDeriver constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\StringTranslation\TranslationManager $string_translation
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    TranslationManager $string_translation
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      if (!$entity_type->get('has_notes')) {
        continue;
      }

      $plugin_id = $entity_type->id();
      $this->derivatives[$plugin_id] = [
        'admin_label' => $this->t(
          'Attach Note to @entity Form',
          [
            '@entity' => $entity_type->getLabel(),
          ]
        ),
        'entity_type' => $entity_type,
        'context' => [
          'entity' => new ContextDefinition(
            'entity:' . $entity_type->id(),
            $this->t(
              'Base @entity_type',
              [
                '@entity_type' => $entity_type->getLabel(),
              ]
            )
          ),
        ],
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }
}
