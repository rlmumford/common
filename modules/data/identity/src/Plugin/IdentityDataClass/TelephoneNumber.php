<?php

namespace Drupal\identity\Plugin\IdentityDataClass;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity\BundleFieldDefinition;
use Drupal\identity\Entity\IdentityData;
use Drupal\identity\IdentityMatch;
use Drupal\telephone\Plugin\Field\FieldType\TelephoneItem;

/**
 * Class TelephoneNumber
 *
 * @IdentityDataClass(
 *   id = "telephone_number",
 *   label = @Translation("Telephone Number"),
 * );
 *
 * @package Drupal\identity\Plugin\IdentityDataClass
 */
class TelephoneNumber extends IdentityDataClassBase {

  const TYPE_HOME = 'home';
  const TYPE_WORK = 'work';
  const TYPE_CELL = 'cell';

  /**
   * @param string $type
   * @param string $reference
   * @param null $value
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\identity\Entity\IdentityData
   */
  public function createData($type, $reference, $value = NULL) {
    $data = parent::createData($type, $reference, $value);

    if (is_string($data)) {
      $data->telephone_number = $data;
    }
    else if (is_array($data)) {
      $data->telephone_number = $value['telephone_number'];
      $data->can_sms = !empty($value['can_sms']);
      $data->can_vm = !empty($value['can_vm']);
    }
    else if ($value instanceof TelephoneItem) {
      $data->telephone_number = $value->toArray();
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['telephone_number'] = BundleFieldDefinition::create('telephone')
      ->setLabel(new TranslatableMarkup('Number'))
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['can_sms'] = BundleFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Can receive SMS messages?'))
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['can_vm'] = BundleFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Can receive Voice Mail'))
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function findMatches(IdentityData $data) {
    /** @var \Drupal\identity\Entity\Query\IdentityDataQueryInterface $query */
    $query = $this->identityDataStorage->getQuery('AND');
    $query->identityDistinct();
    $query->condition('class', $this->pluginId);
    $query->condition('telephone_number', $data->telephone_number->value);

    $matches = [];
    foreach ($this->identityDataStorage->loadMultiple($query->execute()) as $matching_data) {
      /** @var \Drupal\identity\Entity\IdentityData $matching_data */
      $matches[$matching_data->getIdentityId()] = new IdentityMatch(20, $matching_data, $data);
    }

    return $matches;
  }

  /**
   * {@inheritdoc}
   */
  public function supportOrOppose(IdentityData $data, IdentityMatch $match) {
    foreach ($match->getIdentity()->getData($this->getPluginId()) as $identity_data) {
      if ($identity_data->telephone_number->value == $data->telephone_number->value) {
        $match->supportMatch($data, 20);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function typeOptions() {
    return [
      static::TYPE_CELL => new TranslatableMarkup('Cell Phone'),
      static::TYPE_WORK => new TranslatableMarkup('Work'),
      static::TYPE_HOME => new TranslatableMarkup('Home'),
    ];
  }
}
