<?php

namespace Drupal\relationships\Plugin\Field\FieldType;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataReferenceDefinition;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;
use Drupal\relationships\Entity\RelationshipType;

/**
 * Class RelationshipItem
 *
 * This FieldType must ALWAYS be computed
 *
 *  @FieldType(
 *   id = "relationship",
 *   label = @Translation("Relationship"),
 *   description = @Translation("A relationship"),
 *   category = @Translation("Reference"),
 *   default_widget = "inline_relationship_form",
 *   list_class = "\Drupal\relationships\Plugin\Field\RelationshipFieldItemList"
 * )
 *
 * @package Drupal\relationships\Plugin\Field\FieldType
 */
class RelationshipItem extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  protected function getSettings() {
    $settings = parent::getSettings();

    $relationship_type = RelationshipType::load($settings['relationship_type']);
    $settings['target_type'] = $relationship_type->getEntityTypeId($settings['relationship_end']);

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    $relationship_type_info = \Drupal::entityManager()->getDefinition('relationship');
    $properties = static::propertyDefinitions($field_definition)['relationship_id'];
    if ($relationship_type_info->entityClassImplements(FieldableEntityInterface::class) && $properties->getDataType() === 'integer') {
      $schema['columns']['relationship_id'] = [
        'description' => 'The ID of the relationship entity.',
        'type' => 'int',
        'unsigned' => TRUE,
      ];
    }
    else {
      $schema['columns']['relationship_id'] = [
        'description' => 'The ID of the relationship entity.',
        'type' => 'varchar_ascii',
        // If the relationship entities act as bundles for another entity type,
        // their IDs should not exceed the maximum length for bundles.
        'length' => $relationship_type_info->getBundleOf() ? EntityTypeInterface::BUNDLE_MAX_LENGTH : 255,
      ];
    }

    $schema['relationship_id'] = [
      'type' => 'varchar',
      'length' => 255,
      'not null' => TRUE,
      'default' => '',
    ];

    return $schema;
  }

  /**
   * @inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $relationship_type_info = \Drupal::entityManager()->getDefinition('relationship');

    $relationship_id_data_type = 'string';
    if ($relationship_type_info->entityClassImplements(FieldableEntityInterface::class)) {
      $id_definition = \Drupal::entityManager()->getBaseFieldDefinitions('relationship')[$relationship_type_info->getKey('id')];
      if ($id_definition->getType() === 'integer') {
        $relationship_id_data_type = 'integer';
      }
    }

    if ($relationship_id_data_type === 'integer') {
      $relationship_id_definition = DataReferenceTargetDefinition::create('integer')
        ->setLabel(new TranslatableMarkup('@label ID', ['@label' => $relationship_type_info->getLabel()]))
        ->setSetting('unsigned', TRUE);
    }
    else {
      $relationship_id_definition = DataReferenceTargetDefinition::create('string')
        ->setLabel(new TranslatableMarkup('@label ID', ['@label' => $relationship_type_info->getLabel()]));
    }
    $relationship_id_definition->setRequired(TRUE);
    $properties['relationship_id'] = $relationship_id_definition;

    $properties['relationship'] = DataReferenceDefinition::create('entity')
      ->setLabel($relationship_type_info->getLabel())
      ->setDescription(new TranslatableMarkup('The relationship entity'))
      // The entity object is computed out of the entity ID.
      ->setComputed(TRUE)
      ->setReadOnly(FALSE)
      ->setTargetDefinition(EntityDataDefinition::create('relationship'))
      // We can add a constraint for the relationship entity type. The list of
      // referenceable bundles is a field setting, so the corresponding
      // constraint is added dynamically in ::getConstraints().
      ->addConstraint('EntityType', 'relationship');


    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE) {
    // Make sure that the target ID and the target property stay in sync.
    if ($property_name == 'relationship') {
      $property = $this->get('relationship');
      $target_id = $property->isTargetNew() ? NULL : $property->getTargetIdentifier();
      $this->writePropertyValue('relationship_id', $target_id);

      // Get the entity.
      $relationship_end = $this->getFieldDefinition()->getSetting('relationship_end') == 'head' ? 'tail' : 'head';
      $this->writePropertyValue('target_id', $property->getValue()->{$relationship_end}->target_id);
      $this->writePropertyValue('entity', $property->getValue()->{$relationship_end}->target_id);
    }
    elseif ($property_name == 'relationship_id') {
      $this->writePropertyValue('relationship', $this->relationship_id);
      $property = $this->get('relationship');

      // Get the entity.
      $relationship_end = $this->getFieldDefinition()->getSetting('relationship_end') == 'head' ? 'tail' : 'head';
      $this->writePropertyValue('target_id', $property->getValue()->{$relationship_end}->target_id);
      $this->writePropertyValue('entity', $property->getValue()->{$relationship_end}->target_id);
    }

    parent::onChange($property_name, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    if (isset($values) && !is_array($values)) {
      // If either a scalar or an object was passed as the value for the item,
      // assign it to the 'entity' property since that works for both cases.
      $this->set('relationship', $values, $notify);
    }
    else {
      parent::setValue($values, FALSE);

      // Support setting the field item with only one property, but make sure
      // values stay in sync if only property is passed.
      // NULL is a valid value, so we use array_key_exists().
      if (is_array($values) && array_key_exists('relationship_id', $values) && !isset($values['relationship'])) {
        $this->onChange('relationship_id', FALSE);
      }
      elseif (is_array($values) && !array_key_exists('relationship_id', $values) && isset($values['relationship'])) {
        $this->onChange('relationship', FALSE);
      }

      // Notify the parent if necessary.
      if ($notify && $this->parent) {
        $this->parent->onChange($this->getName());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    if (!$this->relationship) {
      return;
    }

    if (($this->relationship->isNew() && !$this->relationship->_needs_delete) || $this->relationship->_needs_save) {
      $this->relationship->save();
    }
    else if ($this->relationship->_needs_delete && !$this->relationship->isNew()) {
      $this->relationship->delete();

      $this->getEntity()->get($this->getFieldDefinition()->getName())->removeItem($this->name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    // Do Nothing
  }
}
