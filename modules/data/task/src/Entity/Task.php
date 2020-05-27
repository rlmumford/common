<?php

namespace Drupal\task\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\task\TaskInterface;

/**
 * Task Entity.
 *
 * @ContentEntityType(
 *   id = "task",
 *   label = @Translation("Task"),
 *   label_singular = @Translation("task"),
 *   label_plural = @Translation("tasks"),
 *   label_count = @PluralTranslation(
 *     singular = "@count task",
 *     plural = "@count tasks"
 *   ),
 *   bundle_label = @Translation("Task Type"),
 *   handlers = {
 *     "list_builder" = "Drupal\task\TaskListBuilder",
 *     "storage" = "Drupal\task\TaskStorage",
 *     "access" = "Drupal\task\TaskAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\task\TaskForm"
 *     },
 *     "views_data" = "Drupal\entity\EntityViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     }
 *   },
 *   has_notes = "true",
 *   base_table = "task",
 *   revision_table = "task_revision",
 *   admin_permission = "administer tasks",
 *   field_ui_base_route = "entity.task.configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "uuid" = "uuid",
 *     "label" = "title"
 *   },
 *   links = {
 *     "collection" = "/task",
 *     "canonical" = "/task/{task}",
 *     "edit-form" = "/task/{task}/edit",
 *     "add-form" = "/task/add"
 *   }
 * )
 */
class Task extends ContentEntityBase implements TaskInterface {

  /**
   * {@inheritdoc}
   */
  public static function statusOptionsList() {
    return [
      static::STATUS_PENDING => t('Pending'),
      static::STATUS_ACTIVE => t('Active'),
      static::STATUS_WAITING => t('Waiting (Blocked)'),
      static::STATUS_RESOLVED => t('Resolved'),
      static::STATUS_CLOSED => t('Closed'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function resolutionOptionsList() {
    return [
      static::RESOLUTION_COMPLETE => t('Complete'),
      static::RESOLUTION_INCOMPLETE => t('Incomplete'),
      static::RESOLUTION_INVALID => t('Invalid'),
      static::RESOLUTION_DUPLICATE => t('Duplicate'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setSetting('allowed_values_function', '\Drupal\task\Entity\Task::statusOptionsList')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'list_default',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    // Important Dates.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'));
    $fields['updated'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Updated'));
    $fields['start'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Start Date'))
      ->setSetting('datetime_type', DateTimeItem::DATETIME_TYPE_DATETIME)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['due'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Due Date'))
      ->setSetting('datetime_type', DateTimeItem::DATETIME_TYPE_DATETIME)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['deadline'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('External Deadline'))
      ->setSetting('datetime_type', DateTimeItem::DATETIME_TYPE_DATETIME)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['resolved'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Date Resolved'))
      ->setSetting('datetime_type', DateTimeItem::DATETIME_TYPE_DATETIME)
      ->setDisplayConfigurable('view', TRUE);

    // Important Users.
    $fields['creator'] = BaseFieldDefinition::create('entity_reference')
      ->setSetting('target_type', 'user')
      ->setLabel(t('Creator'));
    $fields['updater'] = BaseFieldDefinition::create('entity_reference')
      ->setSetting('target_type', 'user')
      ->setLabel(t('Updater'));
    $fields['assignee'] = BaseFieldDefinition::create('entity_reference')
      ->setSetting('target_type', 'user')
      ->setLabel(t('Assigned to'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDefaultValueCallback('Drupal\task\Entity\Task::getCurrentUserId');

    // The Resolution of the Task.
    $fields['resolution'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Resolution'))
      ->setSetting('allowed_values_function', '\Drupal\task\Entity\Task::resolutionOptionsList')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    // Task Dependencies.
    $fields['dependencies'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Dependencies'))
      ->setSetting('target_type', 'task')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['root'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Root Task'))
      ->setSetting('target_type', 'task')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    $current_user = \Drupal::currentUser();

    // Set Creator and Updater.
    if ($this->isNew() && !$this->creator->entity) {
      $this->creator->target_id = $current_user->id();
    }
    $this->updater->target_id = $current_user->id();

    // Set the start date to now if its not already set.
    $now = gmdate(DateTimeItemInterface::DATE_STORAGE_FORMAT);
    if (!$this->start->value) {
      $this->start->value = $now;
    }

    // Set the default status if not set already.
    if (!$this->status->value) {
      $this->status->value = ($this->start->value >= $now) ? 'active' : 'pending';
    }

    if (!in_array($this->status->value, ['closed', 'resolved'])) {
      $open_dependencies = FALSE;
      foreach ($this->dependencies as $item) {
        if (!in_array($item->status->value, ['closed', 'resolved'])) {
          $open_dependencies = TRUE;
          break;
        }
      }

      $this->status->value = $open_dependencies ? 'waiting' : $this->status->value;
    }

    // @todo: Lock tokens if this is resolved.
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    if ($update && $this->status->value != $this->original->status->value) {
      $query = $storage->getQuery();
      $query->condition('dependencies.entity.id', $this->id());
      if ($ids = $query->execute()) {
        foreach ($storage->loadMultiple($ids) as $dependency) {
          $dependency->save();
        }
      }
    }

    // @todo: Process accept criteria.
  }

   /**
   * Default value callback for author.
   */
  public static function getCurrentUserId() {
    return [\Drupal::currentUser()->id()];
  }
}
