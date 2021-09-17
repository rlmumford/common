<?php

namespace Drupal\checklist\Entity;

use Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Definition of the checklist item entity.
 *
 * @ContentEntityType(
 *   id = "checklist_item",
 *   label = @Translation("Checklist Item"),
 *   label_singular = @Translation("checklist item"),
 *   label_plural = @Translation("checklist items"),
 *   label_count = @PluralTranslation(
 *     singular = "@count checklist item",
 *     plural = "@count checklist items"
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "access" = "Drupal\checklist\Entity\ChecklistItemAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   base_table = "checklist_item",
 *   admin_permission = "administer checklist items",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "checklist_type",
 *     "uuid" = "uuid",
 *   },
 *   bundle_label = @Translation("Checklist Type"),
 *   bundle_plugin_type = "checklist_type",
 * )
 *
 * @package Drupal\checklist\Entity
 */
class ChecklistItem extends ContentEntityBase implements ChecklistItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Name'));

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Title'));

    $fields['checklist'] = BaseFieldDefinition::create('checklist_reference')
      ->setLabel(new TranslatableMarkup('Checklist'))
      ->setDescription(new TranslatableMarkup('The checklist this is a part of.'));

    $fields['handler'] = BaseFieldDefinition::create('plugin_reference')
      ->setLabel(new TranslatableMarkup('Handler'))
      ->setDescription(new TranslatableMarkup('The checklist item handler'))
      ->setSetting('plugin_type', 'checklist_item_handler')
      ->setSetting('plugin_creation_callback', static::class . '::createChecklistItemHandlerPluginInstance');

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setSetting('allowed_values', [
        static::STATUS_COMPLETE => new TranslatableMarkup('Complete'),
        static::STATUS_INCOMPLETE => new TranslatableMarkup('Incomplete'),
        static::STATUS_FAILED => new TranslatableMarkup('Failed'),
        static::STATUS_NA => new TranslatableMarkup('Not Applicable'),
      ])
      ->setLabel(new TranslatableMarkup('Status'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['estimate'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Estimate'));

    $fields['attempted'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Attempted'));

    $method_values = [
      static::METHOD_AUTO => 'auto',
      static::METHOD_MANUAL => 'manual',
      static::METHOD_INTERACTIVE => 'interactive',
      static::METHOD_BROKEN => 'broken',
      static::METHOD_RECOVERED => 'recovered',
    ];
    $fields['failure_method'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Failure Method'))
      ->setSetting('allowed_values', $method_values)
      ->setDisplayConfigurable('view', TRUE);

    $fields['completion_method'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Completion Method'))
      ->setSetting('allowed_values', $method_values)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $fields = parent::bundleFieldDefinitions($entity_type, $bundle, $base_field_definitions);

    $checklist_type_definition = \Drupal::service('plugin.manager.checklist_type')
      ->getDefinition($bundle);

    $fields['checklist'] = $base_field_definitions['checklist'];
    $fields['checklist']->setSetting('target_type', $checklist_type_definition['entity_type']);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(): ?bool {
    return (
      $this->status->value === static::STATUS_COMPLETE ||
      (
        $this->status->value !== static::STATUS_NA &&
        $this->getHandler()->isApplicable()
      )
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isComplete(): bool {
    return $this->status->value === static::STATUS_COMPLETE;
  }

  /**
   * {@inheritdoc}
   */
  public function isIncomplete(): bool {
    return $this->status->value === static::STATUS_INCOMPLETE;
  }

  /**
   * {@inheritdoc}
   */
  public function setComplete(string $method = self::METHOD_INTERACTIVE): ChecklistItemInterface {
    $this->status = static::STATUS_COMPLETE;
    $this->completion_method = $method;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setIncomplete(): ChecklistItemInterface {
    $this->status = static::STATUS_INCOMPLETE;
    $this->completion_method = [];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isFailed(): bool {
    return $this->status->value === static::STATUS_FAILED;
  }

  /**
   * {@inheritdoc}
   */
  public function setFailed(string $method = self::METHOD_INTERACTIVE): ChecklistItemInterface {
    $this->status = static::STATUS_FAILED;
    $this->failure_method = $method;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isAttempted(): bool {
    return !$this->attempted->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function setAttempted(): ChecklistItemInterface {
    $this->attempted = TRUE;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isRequired(): bool {
    return $this->getHandler()->isRequired();
  }

  /**
   * {@inheritdoc}
   */
  public function isOptional(): bool {
    return !$this->isRequired();
  }

  /**
   * {@inheritdoc}
   */
  public function isActionable(): bool {
    return $this->getHandler()->isActionable();
  }

  /**
   * {@inheritdoc}
   */
  public function action(): ChecklistItemInterface {
    $this->getHandler()->action();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHandler(): ChecklistItemHandlerInterface {
    return $this->handler->plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->name->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getMethod(): string {
    return $this->getHandler()->getMethod();
  }

  /**
   * Create the plugin for checklist item handler.
   *
   * This method makes sure that the item entity gets set on the handler.
   *
   * @param string $id
   *   The handler id.
   * @param array $configuration
   *   The configuration.
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item.
   *
   * @return \Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface
   *   The checklist item handler.
   */
  public static function createChecklistItemHandlerPluginInstance(string $id, array $configuration, FieldItemInterface $item) {
    /** @var \Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface $plugin */
    $plugin = \Drupal::service('plugin.manager.checklist_item_handler')
      ->createInstance($id, $configuration);
    $plugin->setItem($item->getEntity());
    return $plugin;
  }

}
