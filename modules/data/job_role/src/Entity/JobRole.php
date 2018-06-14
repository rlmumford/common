<?php
/**
 * Created by PhpStorm.
 * User: Mumford
 * Date: 12/06/2018
 * Time: 21:38
 */

namespace Drupal\job_role\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;

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
 *   revision_data_table = "job_role_revision_data",
 *   admin_permission = "administer job_roles",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *     "label" = "title"
 *   }
 * )
 */
class JobRole extends ContentEntityBase implements JobRoleInterface {

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
  public function getOwner() {
    return $this->get('owner')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('owner')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $owner) {
    $this->owner->entity = $owner;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->owner->target_id = $uid;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRevisionable(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'stirng',
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

    $fields['salary'] = BaseFieldDefinition::create('range_decimal')
      ->setSetting('scale', 2)
      ->setLabel(t('Salary'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'range_decimal',
      ])
      ->setDisplayOptions('form', [
        'type' => 'range',
        'settings' => [
          'label' => [
            'from' => t('Between'),
            'to' => t('and'),
          ]
        ]
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['owner'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Owner'))
      ->setDescription(t('The user that owns this role.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default');

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Active'))
      ->setDescription(t('Whether the job_role is active.'))
      ->setDefaultValue(TRUE)
      ->setRevisionable(TRUE);

    $fields['featured'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Featured'))
      ->setDescription(t('Whether the job_role is featured.'))
      ->setDefaultValue(FALSE)
      ->setRevisionable(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time when the job_role was created.'))
      ->setRevisionable(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time when the job_role was last edited.'))
      ->setRevisionable(TRUE);

    $fields['expires'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Expires'))
      ->setDescription(t('The time this job role expires.'))
      ->setRevisionable(TRUE);

    $fields['essential_req'] = BaseFieldDefinition::create('entity_reference_revisions')
      ->setLabel(t('Essential Requirements'))
      ->setDescription(t('Requirements that are essential for the role.'))
      ->setSetting('target_type', 'paragraph')
      ->setSetting('handler', 'default:paragraph')
      ->setSetting('handler_settings', [
        'negate' => 0,
        'target_bundles' => [
          'role_req_responsibility' => 'role_req_responsibility',
          'role_req_education' => 'role_req_education',
          'role_req_skill' => 'role_req_skill',
        ],
      ])
      ->setRevisionable(TRUE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('form', [
        'type' => 'paragraphs',
        'settings' => [
          'title' => 'Essential Requirement',
          'title_plural' => 'Essential Requirements',
          'edit_mode' => 'open',
          'add_mode' => 'dropdown',
          'form_display_mode' => 'default',
        ],
      ])
      ->setDisplayOptions('view', [
        'type' => 'entity_reference_revisions_entity_view',
        'settings' => [
          'view_mode' => 'default',
        ],
      ]);

    $fields['important_req'] = BaseFieldDefinition::create('entity_reference_revisions')
      ->setLabel(t('Important Requirements'))
      ->setDescription(t('Requirements that, although not essential, are important for the role.'))
      ->setSetting('target_type', 'paragraph')
      ->setSetting('handler', 'default:paragraph')
      ->setSetting('handler_settings', [
        'negate' => 0,
        'target_bundles' => [
          'role_req_responsibility' => 'role_req_responsibility',
          'role_req_education' => 'role_req_education',
          'role_req_skill' => 'role_req_skill',
        ],
      ])
      ->setRevisionable(TRUE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('form', [
        'type' => 'paragraphs',
        'settings' => [
          'title' => 'Important Requirement',
          'title_plural' => 'Important Requirements',
          'edit_mode' => 'open',
          'add_mode' => 'dropdown',
          'form_display_mode' => 'default',
        ],
      ])
      ->setDisplayOptions('view', [
        'type' => 'entity_reference_revisions_entity_view',
        'settings' => [
          'view_mode' => 'default',
        ],
      ]);

    $fields['bonus_req'] = BaseFieldDefinition::create('entity_reference_revisions')
      ->setLabel(t('Bonus Requirements'))
      ->setDescription(t('Requirements that, although not essential, would be helpful for the role.'))
      ->setSetting('target_type', 'paragraph')
      ->setSetting('handler', 'default:paragraph')
      ->setSetting('handler_settings', [
        'negate' => 0,
        'target_bundles' => [
          'role_req_responsibility' => 'role_req_responsibility',
          'role_req_education' => 'role_req_education',
          'role_req_skill' => 'role_req_skill',
        ],
      ])
      ->setRevisionable(TRUE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('form', [
        'type' => 'paragraphs',
        'settings' => [
          'title' => 'Bonus Requirement',
          'title_plural' => 'Bonus Requirements',
          'edit_mode' => 'open',
          'add_mode' => 'dropdown',
          'form_display_mode' => 'default',
        ],
      ])
      ->setDisplayOptions('view', [
        'type' => 'entity_reference_revisions_entity_view',
        'settings' => [
          'view_mode' => 'default',
        ],
      ]);

    return $fields;
  }

}