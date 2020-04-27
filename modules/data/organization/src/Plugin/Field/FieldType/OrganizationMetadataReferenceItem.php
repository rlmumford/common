<?php

namespace Drupal\organization\Plugin\Field\FieldType;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Test\TestRunnerKernel;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Class OrganizationMetadataReference
 *
 * @FieldType(
 *   id = "organization_reference",
 *   label = @Translation("Organization metadata reference"),
 *   description = @Translation("A reference to an organization with metadata."),
 *   category = @Translation("Reference"),
 *   default_widget = "entity_reference_autocomplete",
 *   default_formatter = "entity_reference_label",
 *   list_class = "\Drupal\organization\Plugin\Field\OrganizationMetadataReferenceItemList",
 * )
 *
 * @package Drupal\organization\Plugin\Field\FieldType
 */
class OrganizationMetadataReferenceItem extends EntityReferenceItem {

  /**
   * Constants for the status of references to organizations.
   */
  const STATUS_INVITED = 'invited';
  const STATUS_REQUESTED = 'requested';
  const STATUS_ACTIVE = 'active';
  const STATUS_BLOCKED = 'blocked';
  const STATUS_EXPIRED = 'expired';

  /**
   * Constants for describing roles.
   */
  const ROLE_OWNER = 'owner';
  const ROLE_ADMIN = 'admin';
  const ROLE_MEMBER = 'member';
  const ROLE_OBSERVER = 'observer';

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'target_type' => 'organization',
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    // Status
    $schema['columns']['status'] = [
      'type' => 'varchar',
      'length' => 20,
    ];
    $schema['indexes']['status'] = ['status'];

    // Role
    $schema['columns']['role'] = [
      'type' => 'varchar',
      'length' => 20,
    ];
    $schema['indexes']['role'] = ['role'];

    return $schema;
  }

  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['status'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Status'))
      ->addConstraint('Length', ['max' => 20]);

    $properties['role'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Role'))
      ->addConstraint('Length', ['max' => 20]);

    return $properties;
  }
}
