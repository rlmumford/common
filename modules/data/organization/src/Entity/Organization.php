<?php

namespace Drupal\organization\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Class Organization
 *
 * @ContentEntityType(
 *   id = "organization",
 *   label = @TranslatableMarkup("Organization"),
 *   label_singular = @Translation("organization"),
 *   label_plural = @Translation("organizations"),
 *   label_count = @PluralTranslation(
 *     singular = "@count organization",
 *     plural = "@count organizations"
 *   ),
 *   handlers = {
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm"
 *     },
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     }
 *   },,
 *   base_table = "organization",
 *   revision_table = "organization_revision",
 *   admin_permission = "administer organizations",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "uuid" = "uuid",
 *     "label" = "name",
 *     "owner" = "owner",
 *   }
 * )
 *
 * @package Drupal\organization\Entity
 */
class Organization extends ContentEntityBase implements EntityOwnerInterface {
  use EntityOwnerTrait;

  /**
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *
   * @return array|\Drupal\Core\Field\BaseFieldDefinition[]|\Drupal\Core\Field\FieldDefinitionInterface[]
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Name'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'string_default',
        'label' => 'hidden',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['logo'] = BaseFieldDefinition::create('image')
      ->setLabel(new TranslatableMarkup('Logo'))
      ->setSetting('uri_scheme', 'public')
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['legal_name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Legal Name'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'string_default',
        'label' => 'inline',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['website'] = BaseFieldDefinition::create('link')
      ->setLabel(new TranslatableMarkup('Website'))
      ->setDisplayOptions('view', [
        'type' => 'link_default',
      ])
      ->setDisplayOptions('form', [
        'type' => 'link_default',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['tel'] = BaseFieldDefinition::create('telephone')
      ->setLabel(new TranslatableMarkup('Tel.'))
      ->setDisplayOptions('view', [
        'type' => 'telephone_default',
      ])
      ->setDisplayOptions('form', [
        'type' => 'telephone_default',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['email'] = BaseFieldDefinition::create('email')
      ->setLabel(new TranslatableMarkup('E-mail Address.'))
      ->setDisplayOptions('view', [
        'type' => 'email_default',
      ])
      ->setDisplayOptions('form', [
        'type' => 'email_default',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('Description'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'text_default',
        'label' => 'hidden',
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['places'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Places'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('form', [
        'type' => 'inline_entity_form_complex',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}
