<?php

namespace Drupal\identity\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\identity\Entity\IdentityDataInterface;
use Drupal\identity\Entity\IdentityDataClass;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\identity\IdentityMatch;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Entity class for IdentityDatas.
 *
 * @ContentEntityType(
 *   id = "identity_data",
 *   label = @Translation("Identity Data"),
 *   label_singular = @Translation("identity data"),
 *   label_plural = @Translation("identity datas"),
 *   label_count = @PluralTranslation(
 *     singular = "@count identity data",
 *     plural = "@count identity data"
 *   ),
 *   bundle_label = @Translation("Identity Data Class"),
 *   handlers = {
 *     "storage" = "Drupal\identity\Entity\IdentityDataStorage",
 *     "access" = "Drupal\identity\Entity\IdentityDataAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "identity_data",
 *   revision_table = "identity_data_revision",
 *   admin_permission = "administer identity data",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "bundle" = "class",
 *     "uuid" = "uuid",
 *     "owner" = "user",
 *   },
 *   has_notes = "true",
 *   bundle_plugin_type = "identity_data_class",
 *   links = {
 *     "canonical" = "/identity/data/{identity_data}",
 *     "edit-form" = "/identity/data/{identity_data}/edit"
 *   }
 * )
 */
class IdentityData extends ContentEntityBase implements IdentityDataInterface, EntityOwnerInterface {
  use EntityOwnerTrait;

  /**
   * @var \Drupal\identity\Entity\IdentityInterface
   */
  protected $_oldIdentity = NULL;

  /**
   * @var \Drupal\identity\Plugin\IdentityDataClass\IdentityDataClassInterface
   */
  protected $_class;

  /**
   * @var bool
   */
  protected $_skipIdentitySave = FALSE;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Type'))
      ->setRevisionable(TRUE)
      ->setSetting('allowed_values_function', [static::class, 'typeAllowedValues'])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['identity'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Identity'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'identity')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['source'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Source'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'identity_data_source')
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'timestamp',
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['source'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Source'))
      ->setSetting('target_type', 'identity_data_source')
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['reference'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Reference'))
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['archived'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Archived'))
      ->setDefaultValue(['value' => FALSE])
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['group'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('Group'));

    return $fields;
  }

  /**
   * Get the data type plugin.
   *
   * @return \Drupal\identity\Plugin\IdentityDataClass\IdentityDataClassInterface
   */
  public function getClass() {
    if (!$this->_class) {
      $this->_class = \Drupal::service('plugin.manager.identity_data_type')
        ->createInstance($this->class->value);
    }

    return $this->_class;
  }

  /**
   * Get the identity of this data.
   *
   * @return \Drupal\identity\Entity\Identity
   */
  public function getIdentity() {
    return $this->identity->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setIdentity(IdentityInterface $identity) {
    if ($this->identity->entity && $this->identity->entity->id() == $identity->id()) {
      return;
    }

    if ($this->identity->entity) {
      $this->_oldIdentity = $this->identity->entity;
    }

    $this->identity = $identity;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function skipIdentitySave($skip = TRUE) {
    $this->_skipIdentitySave = $skip;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    if (!$this->_skipIdentitySave) {
      $this->getIdentity()->save();
      $this->_oldIdentity->save();

      $this->_skipIdentitySave = FALSE;
    }
  }

  /**
   * Get the acquisition priority of this data.
   *
   * @return integer
   */
  public function acquisitionPriority() {
    return $this->getClass()->acquisitionPriority($this);
  }

  /**
   * Find matches for this data.
   *
   * @return \Drupal\identity\IdentityMatch[]
   */
  public function findMatches() {
    return $this->getClass()->findMatches($this);
  }

  /**
   * Support or oppose a match.
   *
   * @param \Drupal\identity\IdentityMatch $match
   */
  public function supportOrOppose(IdentityMatch $match) {
    return $this->getClass()->supportOrOppose($this, $match);
  }

  /**
   * Get the allowed values for the type field
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $definition
   * @param \Drupal\identity\Entity\IdentityData $entity
   * @param $cacheable
   *
   * @return array
   */
  public static function typeAllowedValues(FieldStorageDefinitionInterface $definition, IdentityData $entity, &$cacheable) {
    $cacheable = FALSE;
    return $entity->getClass()->typeOptions();
  }

}

