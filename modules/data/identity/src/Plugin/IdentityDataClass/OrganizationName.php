<?php

namespace Drupal\identity\Plugin\IdentityDataClass;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity\BundleFieldDefinition;
use Drupal\identity\Entity\IdentityData;
use Drupal\identity\IdentityMatch;

/**
 * Class OrganizationName
 *
 * @IdentityDataClass(
 *   id = "organization_name",
 *   label = @Translation("Organization Name"),
 *   plural_label = @Translation("Organization Names"),
 * );
 *
 * @package Drupal\identity\Plugin\IdentityDataClass
 */
class OrganizationName extends IdentityDataClassBase implements LabelingIdentityDataClassInterface {
  use LabelingIdentityDataClassTrait;

  /**
   * Type constants
   */
  const TYPE_LEGAL = 'legal';
  const TYPE_TRADING = 'trading';
  const TYPE_UNKNOWN = 'unknown';

  /**
   * {@inheritdoc}
   */
  public function typeOptions() {
    return [
      static::TYPE_LEGAL => new TranslatableMarkup('Legal'),
      static::TYPE_TRADING => new TranslatableMarkup('Trading Name'),
      static::TYPE_UNKNOWN => new TranslatableMarkup('Unknown'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function createData($type, $reference, $value = NULL) {
    $data = parent::createData($type, $reference, $value);
    $data->org_name = $value;
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['org_name'] = BundleFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Name'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'string',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function dataLabel(IdentityData $data) {
    return $data->org_name->value;
  }

  /**
   * {@inheritdoc}
   */
  public function findMatches(IdentityData $data) {
    /** @var \Drupal\identity\Entity\Query\IdentityDataQueryInterface $query */
    $query = $this->identityDataStorage->getQuery();
    $query->identityDistinct();
    $query->condition('class', $this->pluginId);
    $query->exists('identity');

    // This is a bit artificial.
    $query->sort('identity');
    $query->range(0 , 50);

    if ($data->org_name->value) {
      $query->condition('org_name', $data->org_name->value);
    }
    else {
      return [];
    }

    $matches = [];
    foreach ($this->identityDataStorage->loadMultiple($query->execute()) as $match_data) {
      /** @var IdentityData $match_data */
      if ($match_data->getIdentityId() && empty($matches[$match_data->getIdentityId()])) {
        $matches[$match_data->getIdentityId()]
          = new IdentityMatch($data, $match_data, 10);
      }
    }

    return $matches;
  }

  /**
   * Work out whether the data supports or opposes
   *
   * @param \Drupal\identity\Entity\IdentityData $data
   * @param \Drupal\identity\IdentityMatch $match
   */
  public function supportOrOppose(IdentityData $search_data, IdentityMatch $match) {
    $query = $this->identityDataStorage->getQuery();
    $query->identityDistinct();
    $query->condition('identity', $match->getIdentityId());
    $query->condition('org_name', $search_data->org_name->value);

    if ($ids = $query->execute()) {
      $match->supportMatch($search_data, $this->identityDataStorage->load(reset($ids)), 10);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function buildIdentityLabel(IdentityData $data) {
    return $data->org_name->value;
  }
}
