<?php

namespace Drupal\identity\Plugin\IdentityDataClass;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity\BundleFieldDefinition;
use Drupal\identity\Entity\Identity;
use Drupal\identity\Entity\IdentityData;
use Drupal\identity\Entity\IdentityDataInterface;
use Drupal\identity\IdentityLabelContext;
use Drupal\identity\IdentityMatch;
use Drupal\name\Plugin\Field\FieldType\NameItem;

/**
 * Class PersonalName
 *
 * @IdentityDataClass(
 *   id = "personal_name",
 *   label = @Translation("Personal Name"),
 *   plural_label = @Translation("Personal Names"),
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
      ->setDisplayOptions('view', [
        'type' => 'string',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['name'] = BundleFieldDefinition::create('name')
      ->setLabel(new TranslatableMarkup('Name'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'name_default',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'name_default',
      ])
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
  public function dataLabel(IdentityData $data) {
    if (!$data->name->isEmpty()) {
      $render = $data->name->view(['type' => 'name_default']);
      return $render[0]['#markup'];
    }
    else {
      return $data->full_name->value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function findMatches(IdentityData $data) {
    /** @var \Drupal\identity\Entity\Query\IdentityDataQueryInterface $query */
    $query = $this->identityDataStorage->getQuery('AND');
    $query->identityDistinct();
    $query->condition('class', $this->pluginId);
    $query->exists('identity');
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

    // If we have more than 100 matches, it would be better
    // to start from the address or SSN, so lets not clog up
    // the matcher with pointless names.
    if ((clone $query)->count()->execute() > 100) {
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
   * @param \Drupal\identity\Entity\IdentityData $search_data
   * @param \Drupal\identity\IdentityMatch $match
   */
  public function supportOrOppose(IdentityData $search_data, IdentityMatch $match) {
    $identity = $match->getIdentity();

    // Only match on full or legal.
    if (!in_array($search_data->type->value, [static::TYPE_FULL, static::TYPE_LEGAL])) {
      return;
    }

    /** @var \Drupal\identity\Entity\IdentityData $match_data */
    foreach ($identity->getData($this->pluginId) as $match_data) {
      if (!in_array($match_data->type->value, [static::TYPE_FULL, static::TYPE_LEGAL])) {
        continue;
      }

      if (!$search_data->name->isEmpty()) {
        $levels = [];
        $score = 0;

        if ($search_data->name->given == $match_data->name->given) {
          $levels[] = 'first';
          $score += 10;
        }

        if ($search_data->name->family == $match_data->name->family) {
          $levels[] = 'family';
          $score += 10;
        }

        if ($search_data->name->generational == $match_data->name->generational) {
          $levels[] = 'suffix';
        }
        else {
          $score -= 10;
        }

        if ($match->supportMatch($search_data, $match_data, $score, $levels)) {
          return;
        }
      }
      else if (!$search_data->full_name->isEmpty() && $search_data->full_name->value == $match_data->full_name->value) {
        if ($match->supportMatch($search_data, $match_data, 10, ['full'])) {
          return;
        }
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
    if (!$data->name->isEmpty()) {
      $render = $data->name->view(['type' => 'name_default']);
      return $render[0]['#markup'];
    }
    else {
      return $data->full_name->value;
    }
  }

  public function possibleMatchSupportLevels(IdentityDataInterface $search_data) {
    if (!$search_data->name->isEmpty()) {
      return ['first', 'family', 'suffix'];
    }
    else {
      return ['full'];
    }
  }
}
