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
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'timestamp',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  public static function createLabel(Identity $entity, FieldDefinitionInterface $definition) {
    return \Drupal::service('identity.labeler')->label($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getData($class, array $filters = []) {
    if (!isset($this->_data[$class])) {
      $data_storage = \Drupal::entityTypeManager()->getStorage('identity_data');
      $query = $data_storage->getQuery();
      $query->condition('identity', $this->id());
      $query->condition('class', $class);

      $this->_data[$class] = $data_storage->loadMultiple($query->execute());
    }

    return $this->applyDataFilters($this->_data[$class], $filters);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllData(array $filters = []) {
    /** @var \Drupal\identity\Entity\IdentityDataStorage $data_storage */
    $data_storage = \Drupal::entityTypeManager()->getStorage('identity_data');
    /** @var \Drupal\identity\IdentityDataClassManager $data_type_manager */
    $data_type_manager = \Drupal::service('plugin.manager.identity_data_class');
    $classes = $data_type_manager->getDefinitions();

    $all_data = [];
    $unloaded_classes = [];
    foreach ($classes as $class => $definition) {
      if (isset($this->_data[$class])) {
        $all_data += $this->_data[$class];
      }
      else {
        $unloaded_classes[] = $class;
      }
    }

    if (!empty($unloaded_classes)) {
      $query = $data_storage->getQuery();
      $query->condition('class', $unloaded_classes, 'IN');
      $query->condition('identity', $this->id());

      /** @var \Drupal\identity\Entity\IdentityData[] $loaded_data */
      $loaded_data = $data_storage->loadMultiple($query->execute());
      foreach ($loaded_data as $loaded_datum) {
        $all_data[$loaded_datum->id()] = $loaded_datum;
        $this->_data[$loaded_datum->bundle()][$loaded_datum->id()] = $loaded_datum;
      }
    }

    return $this->applyDataFilters($all_data, $filters);
  }

  /**
   * @param \Drupal\identity\Entity\IdentityDataInterface[] $data
   * @param array $filters
   */
  protected function applyDataFilters($data, array $filters = []) {
    if (empty($filters)) {
      return $data;
    }

    return array_filter($data, function($data) use ($filters) {
      foreach ($filters as $field_name => $value) {
        if ($data->{$field_name}->value != $value) {
          return FALSE;
        }
      }

      return TRUE;
    });
  }
}

