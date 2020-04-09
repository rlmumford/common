<?php

namespace Drupal\identity\Plugin\IdentityDataClass;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity\BundleFieldDefinition;
use Drupal\identity\Entity\IdentityData;
use Drupal\identity\IdentityMatch;

/**
 * Class Role
 *
 *  @IdentityDataClass(
 *   id = "role",
 *   label = @Translation("Role"),
 *   plural_label = @Translation("Roles"),
 * );
 *
 * @package Drupal\identity\Plugin\IdentityDataClass
 */
class Role extends IdentityDataClassBase {

  /**
   * Type constants
   */
  const TYPE_UNIVERSAL = 'universal';
  const TYPE_FAMILY = 'family';
  const TYPE_ACTIVITY = 'activity';
  const TYPE_ORGANIZATION = 'organization';

  /**
   * Role constants.
   */
  const ROLE_INDIVIDUAL = 'individual';
  const ROLE_ORGANIZATION = 'organization';

  /**
   * {@inheritdoc}
   */
  public function typeOptions() {
    return [
      static::TYPE_UNIVERSAL => new TranslatableMarkup('Universal'),
      static::TYPE_FAMILY => new TranslatableMarkup('Familial'),
      static::TYPE_ACTIVITY => new TranslatableMarkup('Activity'),
      static::TYPE_ORGANIZATION => new TranslatableMarkup('Organizational'),
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

    $fields['role'] = BundleFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Role'))
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['role_context_id'] = BundleFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Context ID'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['role_context_label'] = BundleFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Context Label'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['role_context_url'] = BundleFieldDefinition::create('uri')
      ->setLabel(new TranslatableMarkup('Context URI'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * [@inheritdoc}
   */
  public function supportOrOppose(IdentityData $search_data, IdentityMatch $match) {
    if ($search_data->type->value !== static::TYPE_UNIVERSAL) {
      return;
    }

    if (!in_array($search_data->role->value, [static::ROLE_INDIVIDUAL, static::ROLE_ORGANIZATION])) {
      return;
    }

    // Try and find a role data that matches the opposite universal role on this
    // identity.
    $excluding_role = $search_data->role->value === static::ROLE_ORGANIZATION ?
      static::ROLE_INDIVIDUAL :
      static::ROLE_ORGANIZATION;
    $data_ids = $this->identityDataStorage->getQuery()
      ->condition('identity', $match->getIdentityId())
      ->condition('class', $this->getPluginId())
      ->condition('type', static::TYPE_UNIVERSAL)
      ->condition('role', $excluding_role)
      // We only care if there is atleast one.
      ->range(0, 1)
      ->execute();

    if (!empty($data_ids)) {
      $match->opposeMatch(
        $search_data,
        $this->identityDataStorage->load(reset($data_ids)),
        -10,
        ['is_' . $excluding_role]
      );
    }
  }
}
