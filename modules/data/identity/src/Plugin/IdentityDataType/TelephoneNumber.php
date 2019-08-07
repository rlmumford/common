<?php

namespace Drupal\identity\Plugin\IdentityDataType;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity\BundleFieldDefinition;
use Drupal\identity\Entity\IdentityData;
use Drupal\identity\IdentityMatch;

/**
 * Class TelephoneNumber
 *
 * @IdentityDataType(
 *   "id" = "telephone_number",
 *   "label" = @Translation("Telephone Number"),
 * );
 *
 * @package Drupal\identity\Plugin\IdentityDataType
 */
class TelephoneNumber extends IdentityDataTypeBase {

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

    $fields['telephone_type'] = BundleFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Type'))
      ->setSetting('allowed_values', [
        // @todo: Telephone type.
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function findMatches(IdentityData $data) {
    $query = $this->identityDataStorage->getQuery('AND');
    $query->condition('type', $this->pluginId);
    $query->condition('telephone_number', $data->telephone_number->value);

    $matches = [];
    foreach ($this->identityDataStorage->loadMultiple($query->execute()) as $matching_data) {
      /** @var \Drupal\identity\Entity\IdentityData $matching_data */
      $matches[$matching_data->getIdentity()->id()] = new IdentityMatch(20, $matching_data, $data);
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
}
