<?php

namespace Drupal\job_role\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\organization\Entity\EntityOrganizationInterface;
use Drupal\organization\Entity\EntityOrganizationTrait;
use Drupal\user\EntityOwnerTrait;

/**
 * Job Role Entity.
 *
 * @ContentEntityType(
 *   id = "job_role",
 *   label = @Translation("Job"),
 *   label_singular = @Translation("job"),
 *   label_plural = @Translation("jobs"),
 *   label_count = @PluralTranslation(
 *     singular = "@count job",
 *     plural = "@count jobs"
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\job_role\JobRoleListBuilder",
 *     "storage" = "Drupal\job_role\JobRoleStorage",
 *     "access" = "Drupal\job_role\JobRoleAccessControlHandler",
 *     "permission_provider" = "Drupal\job_role\JobRolePermissionProvider",
 *     "form" = {
 *       "default" = "Drupal\job_role\JobRoleForm"
 *     },
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "job_role",
 *   revision_table = "job_role_revision",
 *   data_table = "job_role_data",
 *   field_ui_base_route = "entity.job_role.admin_form",
 *   revision_data_table = "job_role_revision_data",
 *   admin_permission = "administer job roles",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *     "owner" = "owner",
 *     "organization" = "organization",
 *   }
 * )
 */
class JobRole extends ContentEntityBase implements JobRoleInterface {
  use EntityOwnerTrait;
  use EntityChangedTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function isActive() {
    return (bool) $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setActive($active) {
    $this->set('status', (bool) $active);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRevisionable(TRUE)
      ->setSetting('max_length', 255)
      ->setRequired(TRUE)
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
      ->setLabel(t('Full Job Description'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['pay'] = BaseFieldDefinition::create('job_role_salary')
      ->setLabel(new TranslatableMarkup('Salary'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'job_role_salary_default',
      ])
      ->setDisplayOptions('form', [
        'type' => 'job_role_salary_default',
        'settings' => [
          'label' => [
            'from' => t('Between'),
            'to' => t('and'),
          ]
        ]
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['files'] = BaseFieldDefinition::create('file')
      ->setCardinality(2)
      ->setLabel(t('Supporting Documents'))
      ->setDescription(t('Supporting documentation associated with this role.'))
      ->setRevisionable(TRUE)
      ->setSetting('file_extensions', 'pdf txt doc docx')
      ->setSetting('uri_scheme', 'public')
      ->setSetting('description_field', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'file_generic',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'file_default',
        'label' => 'above',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['organization'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('User ID'))
      ->setSetting('target_type', 'organization')
      ->setTranslatable($entity_type->isTranslatable())
      ->setDefaultValueCallback(static::class . '::getDefaultEntityOrganization');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time when the job_role was created.'))
      ->setRevisionable(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time when the job_role was last edited.'))
      ->setRevisionable(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganization() {
    $key = $this->getEntityType()->getKey('organization');
    return $this->{$key}->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setOrganization(EntityInterface $organization) {
    $key = $this->getEntityType()->getKey('organization');
    $this->{$key} = $organization;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaultEntityOrganization(EntityInterface $entity, FieldDefinitionInterface $field_definition) {
    return NULL;
  }

}
