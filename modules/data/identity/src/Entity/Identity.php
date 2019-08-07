<?php

namespace Drupal\identity\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Entity class for Identities.
 *
 * @ContentEntityType(
 *   id = "identity",
 *   label = @Translation("Identity"),
 *   label_singular = @Translation("identity"),
 *   label_plural = @Translation("identities"),
 *   label_count = @PluralTranslation(
 *     singular = "@count identity",
 *     plural = "@count identities"
 *   ),
 *   bundle_label = @Translation("Identity Type"),
 *   handlers = {
 *     "storage" = "Drupal\identity\Entity\IdentityStorage",
 *     "access" = "Drupal\identity\Entity\IdentityAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "identity",
 *   revision_table = "identity_revision",
 *   admin_permission = "administer identities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *   },
 *   has_notes = "true",
 *   links = {
 *     "canonical" = "/identity/{identity}",
 *   }
 * )
 */
class Identity extends ContentEntityBase implements IdentityInterface {

  /**
   * @var \Drupal\identity\Entity\IdentityData[][]
   */
  protected $_data;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRevisionable(TRUE)
      ->setDefaultValueCallback('\Drupal\identity\Entity\Identity::createLabel')
      ->setDisplayConfigurable('view', TRUE);

    $fields['state'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Active?'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
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

    return $fields;
  }

  /**
   * @param $type
   * @param array $filters
   */
  public function getData($type, array $filters = []) {
    if (!isset($this->_data[$type])) {
      $data_storage = \Drupal::entityTypeManager()->getStorage('identity_data');
      $query = $data_storage->getQuery();
      $query->condition('identity', $this->id());
      $query->condition('type', $type);

      $this->_data[$type] =  $data_storage->loadMultiple($query->execute());
    }

    if (empty($filters)) {
      return $this->_data[$type];
    }

    return array_filter($this->_data[$type], function($data) use ($filters) {
      foreach ($filters as $field_name => $value) {
        if ($data->{$field_name}->value != $value) {
          return FALSE;
        }
      }

      return TRUE;
    });
  }
}

