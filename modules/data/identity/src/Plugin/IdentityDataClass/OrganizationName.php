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
    $data->name = $value;
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
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function findMatches(IdentityData $data) {
    $query = $this->identityDataStorage->getQuery();
    $query->condition('class', $this->pluginId);
    $query->exists('identity');
    if ($data->org_name->value) {
      $query->condition('org_name', $data->org_name->value);
    }
    else {
      return [];
    }

    // If there are more than 100 organizations with this name,
    // its unlikely that we are going to be able a decent match
    // from here, so lets not bother.
    if ((clone $query)->count()->execute() > 100) {
      return [];
    }

    $matches = [];
    foreach ($this->identityDataStorage->loadMultiple($query->execute()) as $match_data) {
      /** @var IdentityData $match_data */
      if ($match_data->getIdentity()) {
        $matches[$match_data->getIdentity()->id()]
          = new IdentityMatch(10, $match_data, $data);
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
  public function supportOrOppose(IdentityData $data, IdentityMatch $match) {
    $identity = $match->getIdentity();
    foreach ($identity->getData($this->pluginId) as $identity_data) {
      if ($data->org_name->value == $identity_data->org_name->value) {
        $match->supportMatch($identity_data, 10);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function buildIdentityLabel(IdentityData $data) {
    return $data->org_name->value;
  }
}
