<?php

namespace Drupal\identity\Plugin\IdentityDataType;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity\BundleFieldDefinition;
use Drupal\identity\Entity\IdentityData;
use Drupal\identity\IdentityMatch;

/**
 * Class PersonalName
 *
 * @IdentityDataType(
 *   id = "personal_name",
 *   label = @Translation("Personal Name"),
 * );
 *
 * @package Drupal\identity\Plugin\IdentityDataType
 */
class PersonalName extends IdentityDataTypeBase {

  const TYPE_ALIAS = 'alias';
  const TYPE_FULL = 'full';
  const TYPE_LEGAL = 'legal';
  const TYPE_NICK = 'nick';

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['full_name'] = BundleFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Full Name'))
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['name'] = BundleFieldDefinition::create('name')
      ->setLabel(new TranslatableMarkup('Name'))
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['name_type'] = BundleFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Type'))
      ->setSetting('allowed_values', [
        static::TYPE_ALIAS => new TranslatableMarkup('Alias'),
        static::TYPE_LEGAL => new TranslatableMarkup('Legal'),
        static::TYPE_FULL => new TranslatableMarkup('Full'),
        static::TYPE_NICK => new TranslatableMarkup('Nickname'),
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['is_formal'] = BundleFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Is Formal'))
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
    $or_condition = $query->orConditionGroup();

    $not_enough_data = TRUE;
    if ($data->full_name->value) {
      // @todo: Find a way to use SOUNDEX() to do some fuzzy matching
      $not_enough_data = FALSE;
      $or_condition->condition('full_name', $data->full_name->value);
    }
    if ($data->name->given && $data->name->family) {
      $not_enough_data = FALSE;
      $or_condition->condition(
        $query->andConditionGroup()
          ->condition('name.given', $data->name->given)
          ->condition('name.family', $data->name->family)
      );
    }
    $query->condition($or_condition);

    if ($not_enough_data) {
      return [];
    }

    $matches = [];
    foreach ($this->identityDataStorage->loadMultiple($query->execute()) as $match_data) {
      /** @var IdentityData $match_data */
      $matches[$match_data->getIdentity()->id()] = new IdentityMatch(10, $match_data, $data);
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

    // Only match on full or legal.
    if (!in_array($data->type->value, [static::TYPE_FULL, static::TYPE_LEGAL])) {
      return;
    }

    foreach ($identity->getData($this->pluginId) as $identity_data) {
      if (!in_array($identity_data->type->value, [static::TYPE_FULL, static::TYPE_LEGAL])) {
        continue;
      }

      if ($data->full_name->value == $identity_data->full_name->value) {
        $match->supportMatch($identity_data, 10);
      }
      else if ($data->name->given == $identity_data->name->given && $data->name->family == $identity_data->name->family) {
        $match->supportMatch($identity_data, 8);
      }

      // @todo: Consider opposing matches.
    }
  }
}
