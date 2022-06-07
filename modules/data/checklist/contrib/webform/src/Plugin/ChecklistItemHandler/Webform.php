<?php

namespace Drupal\checklist_webform\Plugin\ChecklistItemHandler;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerBase;
use Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ExpectedOutcomeChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\InteractiveChecklistItemHandlerInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\webform\Entity\Webform as WebformConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Checklist item handler that presents a webform to the user.
 *
 * @ChecklistItemHandler(
 *   id = "webform",
 *   label = @Translation("Fill in Webform"),
 *   category = @Translation("Forms"),
 *   forms = {
 *     "row" = "\Drupal\checklist\PluginForm\StartableItemRowForm",
 *     "action" = "\Drupal\checklist_webform\PluginForm\WebformActionForm",
 *     "configure" = "\Drupal\checklist_webform\PluginForm\WebformConfigureForm",
 *   }
 * )
 */
class Webform extends ChecklistItemHandlerBase implements ContainerFactoryPluginInterface, InteractiveChecklistItemHandlerInterface, ExpectedOutcomeChecklistItemHandlerInterface {
  use DependencySerializationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The webform.
   *
   * @var \Drupal\webform\Entity\Webform|null
   */
  protected ?WebformConfig $webform = NULL;

  /**
   * Create a new instance of the webform plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
  }

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
   * Get the webform.
   *
   * @return \Drupal\webform\Entity\Webform|null
   *   The webform config.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getWebform() : ?WebformConfig {
    $configuration = $this->getConfiguration();

    if (!$this->webform && !empty($configuration['webform'])) {
      $this->webform = $this->entityTypeManager
        ->getStorage('webform')
        ->load($configuration['webform']);

      if ($this->webform) {
        $this->webform->setSetting('ajax', TRUE);
      }
    }

    return $this->webform;
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
  public function buildConfigurationSummary(): array {
    $build = [];
    $build['webform'] = [
      '#type' => 'item',
      '#title' => new TranslatableMarkup('Webform'),
      '#markup' => $this->getWebform() ?
      $this->getWebform()->toLink(NULL, 'edit-form')->toString() :
      new TranslatableMarkup('Not Defined'),
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = parent::defaultConfiguration();
    $config['webform'] = NULL;
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function expectedOutcomeDefinitions(): array {
    return [
      'submission' => EntityDataDefinition::create('webform_submission', $this->configuration['webform'] ?? NULL)
        ->setLabel('Webform Submission'),
    ];
  }

}
