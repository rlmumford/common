<?php

namespace Drupal\identity\Plugin\IdentityDataClass;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\entity\BundleFieldDefinition;

/**
 * Class MemberOf
 *
 * @IdentityDataClass(
 *   id = "member_of",
 *   label = @Translation("Member Of"),
 * );
 *
 * @package Drupal\identity\Plugin\IdentityDataClass
 */
class MemberOf extends RelationshipIdentityDataClassBase {

  const TYPE_EMPLOYEE = 'employee';
  const TYPE_DIRECTOR = 'director';
  const TYPE_OWNER = 'owner';

  /**
   * {@inheritdoc}
   */
  public function typeOptions() {
    return [
      static::TYPE_EMPLOYEE => new TranslatableMarkup('Employee'),
      static::TYPE_DIRECTOR => new TranslatableMarkup('Director'),
      static::TYPE_OWNER => new TranslatableMarkup('Ownder'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['role'] = BundleFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Role'))
      ->setDescription(new TranslatableMarkup('The role the identity has at the organisation'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['start_date'] = BundleFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Start Date'))
      ->setSetting('datetime_type', DateTimeItem::DATETIME_TYPE_DATE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['end_date'] = BundleFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('End Date'))
      ->setSetting('datetime_type', DateTimeItem::DATETIME_TYPE_DATE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function initMetadata($data, $metadata) {
    if (isset($metadata['role'])) {
      $data->role = $metadata['role'];
    }

    foreach (['start_date', 'end_date'] as $date_field) {
      if (isset($metadata[$date_field])) {
        $data->{$date_field} = $metadata[$date_field];
      }
    }
  }
}
