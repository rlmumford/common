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
 * Class CreateEntity
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
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
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
   * Action the checklist item.
   *
   * @return \Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface
   */
  public function action(): ChecklistItemHandlerInterface {
    if ($this->getMethod() == ChecklistItemInterface::METHOD_AUTO) {
      $entity = $this->doCreateEntity();
      $entity->save();

      // @todo: Some approximation of outcomes.

      $this->getItem()->setComplete(ChecklistItemInterface::METHOD_AUTO);
      $this->getItem()->save();
    }

    return $this;
  }

  /**
   * Build the configuration summary.
   *
   * @return array
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
   *
   * @return \Drupal\Core\Entity\EntityInterface
   */
  public function doCreateEntity($bundle = NULL) : EntityInterface {
    $values = [];
    if ($this->entityType->hasKey('bundle')) {
      $values[$this->entityType->getKey('bundle')] = $bundle;
    }
    return $this->entityStorage->create($values);
  }

  /**
   * Get the entity type interface
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   */
  public function getEntityType() : EntityTypeInterface {
    return $this->entityType;
  }
}
