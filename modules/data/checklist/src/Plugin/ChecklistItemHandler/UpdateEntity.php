<?php

namespace Drupal\checklist\Plugin\ChecklistItemHandler;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create entity checklist item handler.
 *
 * @ChecklistItemHandler(
 *   id = "update_entity",
 *   label = @Translation("Update Entity"),
 *   category = @Translation("Entity"),
 *   deriver = "Drupal\checklist\Plugin\Derivative\ContentEntityTypeDeriver",
 *   entity_op = @Translation("Update"),
 *   forms = {
 *     "row" = "\Drupal\checklist\PluginForm\StartableItemRowForm",
 *     "action" = "\Drupal\checklist\PluginForm\UpdateEntityItemActionForm",
 *     "configure" = "\Drupal\checklist\PluginForm\UpdateEntityItemConfigureForm",
 *   },
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity", required = TRUE, label = @Translation("Entity")),
 *   }
 * )
 *
 * @package Drupal\checklist\Plugin\ChecklistItemHandler
 */
class UpdateEntity extends ContextAwareChecklistItemHandlerBase implements ContainerFactoryPluginInterface, InteractiveChecklistItemHandlerInterface {
  use DependencySerializationTrait;

  /**
   * The entity display repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity type service.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected EntityTypeInterface $entityType;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * Create an update_entity plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   the entity display repository.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityDisplayRepositoryInterface $entity_display_repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityDisplayRepository = $entity_display_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityType = $this->entityTypeManager->getDefinition($plugin_definition['entity_type']);
  }

  /**
   * {@inheritdoc}
   */
  public function getMethod(): string {
    return ChecklistItemInterface::METHOD_INTERACTIVE;
  }

  /**
   * {@inheritdoc}
   */
  public function action(): ChecklistItemHandlerInterface {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'form_mode' => 'default',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary(): array {
    $build = parent::buildConfigurationSummary();

    $conf = $this->getConfiguration();
    $build['form_mode'] = [
      '#type' => 'item',
      '#title' => new TranslatableMarkup('Form Mode'),
      '#markup' => $conf['form_mode'] ?
        $this->entityDisplayRepository->getFormModeOptions($this->entityType->id())[$conf['form_mode']] :
        'Unknown'
    ];

    return $build;
  }

}
