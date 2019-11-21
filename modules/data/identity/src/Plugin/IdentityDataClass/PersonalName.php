<?php

namespace Drupal\identity\Plugin\IdentityDataClass;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity\BundleFieldDefinition;
use Drupal\identity\Entity\Identity;
use Drupal\identity\Entity\IdentityData;
use Drupal\identity\IdentityLabelContext;
use Drupal\identity\IdentityMatch;
use Drupal\name\Plugin\Field\FieldType\NameItem;

/**
 * Class PersonalName
 *
 * @IdentityDataClass(
 *   id = "personal_name",
 *   label = @Translation("Personal Name"),
 * );
 *
 * @package Drupal\identity\Plugin\IdentityDataClass
 */
class PersonalName extends IdentityDataClassBase implements LabelingIdentityDataClassInterface {
  use LabelingIdentityDataClassTrait;

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
    $query->condition('class', $this->pluginId);
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

  /**
   * {@inheritdoc}
   */
  public function typeOptions() {
    return [
      static::TYPE_FULL => new TranslatableMarkup('Full Name'),
      static::TYPE_LEGAL => new TranslatableMarkup('Legal Name'),
      static::TYPE_ALIAS => new TranslatableMarkup('Alias'),
      static::TYPE_NICK => new TranslatableMarkup('Nickname'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function createData($type, $reference, $value = NULL) {
    $data = parent::createData($type, $reference, $value);

    if (is_array($value)) {
      $data->name = $value;
      $data->full_name = "{$value['given']} {$value['family']}";
    }
    else if (is_string($value)) {
      $data->full_name = $value;
    }
    else if ($value instanceof NameItem) {
      $data->name = $value->toArray();
      $data->full_name = "{$value->given} {$value->family}";
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildIdentityLabel(IdentityData $data) {
    return $data->full_name->value;
  }
}
