<?php

namespace Drupal\checklist\Plugin\ChecklistItemHandler;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create entity checklist item handler.
 *
 * @ChecklistItemHandler(
 *   id = "create_entity",
 *   label = @Translation("Create Entity"),
 *   deriver = "Drupal\checklist\Plugin\Derivative\ContentEntityTypeDeriver",
 *   forms = {
 *     "row" = "\Drupal\checklist\PluginForm\StartableItemRowForm",
 *     "action" = "\Drupal\checklist\PluginForm\CreateEntityItemActionForm",
 *     "configure" = "\Drupal\checklist\PluginForm\CreateEntityItemConfigureForm",
 *   }
 * )
 *
 * @package Drupal\checklist\Plugin\ChecklistItemHandler
 */
class CreateEntity extends ChecklistItemHandlerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * The entity type bundle service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getDefinition($plugin_definition['entity_type']),
      $container->get('entity_type.manager')->getStorage($plugin_definition['entity_type']),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * CreateEntity constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type being created.
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_storage
   *   The entity type storage.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    EntityTypeInterface $entity_type,
    EntityStorageInterface $entity_storage,
    EntityTypeBundleInfoInterface $entity_type_bundle_info
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityType = $entity_type;
    $this->entityStorage = $entity_storage;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'bundle' => $this->entityType->hasKey('bundle') ? '__select' : $this->entityType->id(),
      'show_form' => TRUE,
      'form_mode' => 'add',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getMethod(): string {
    $configuration = $this->getConfiguration();

    if ($configuration['bundle'] !== '__select' && empty($configuration['show_form'])) {
      return ChecklistItemInterface::METHOD_AUTO;
    }

    return ChecklistItemInterface::METHOD_INTERACTIVE;
  }

  /**
   * {@inheritdoc}
   */
  public function action(): ChecklistItemHandlerInterface {
    if ($this->getMethod() == ChecklistItemInterface::METHOD_AUTO) {
      $entity = $this->doCreateEntity();
      $entity->save();

      // @todo Some approximation of outcomes.
      $this->getItem()->setComplete(ChecklistItemInterface::METHOD_AUTO);
      $this->getItem()->save();
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary(): array {
    $build = [
      'title' => [
        '#markup' => $this->pluginDefinition['label'],
      ],
    ];

    if ($this->entityType->hasKey('bundle')) {
      $configuration = $this->getConfiguration();

      $build['bundle'] = [
        '#type' => 'item',
        '#title' => ucfirst($this->entityType->getKey('bundle')),
        '#markup' => $configuration['bundle'] === '__select' ?
        $this->t('Selected by User') :
        $this->entityTypeBundleInfo->getBundleInfo(
            $this->entityType->id()
        )[$configuration['bundle']]['label'],
      ];
    }

    return $build;
  }

  /**
   * Get the entity.
   *
   * @param string|null $bundle
   *   The bundle to create.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The created entity.
   */
  public function doCreateEntity($bundle = NULL) : EntityInterface {
    $values = [];
    if ($this->entityType->hasKey('bundle')) {
      $values[$this->entityType->getKey('bundle')] = $bundle;
    }
    return $this->entityStorage->create($values);
  }

  /**
   * Get the entity type interface.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The entity type being created.
   */
  public function getEntityType() : EntityTypeInterface {
    return $this->entityType;
  }

}
