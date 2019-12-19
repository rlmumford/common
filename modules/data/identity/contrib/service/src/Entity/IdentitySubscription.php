<?php

namespace Drupal\identity_service\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\identity\Event\IdentityEvents;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Class IdentitySubscription
 *
 * @ContentEntityType(
 *   id = "identity_subscription",
 *   label = @Translation("Identity Subscription"),
 *   label_singular = @Translation("identity subscription"),
 *   label_plural = @Translation("identity subscriptions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count identity subscription",
 *     plural = "@count identity subscriptions"
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\identity_service\Entity\IdentitySubscriptionStorage",
 *     "access" = "Drupal\identity_service\Entity\IdentitySubscriptionAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData"
 *   },
 *   base_table = "identity_subscription",
 *   admin_permission = "administer identity subscriptions",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "owner",
 *   }
 * )
 *
 * @package Drupal\identity_service\Entity
 */
class IdentitySubscription extends ContentEntityBase implements EntityOwnerInterface {
  use EntityOwnerTrait;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['identity'] = BaseFieldDefinition::create('entity_reference')
      ->setSetting('target_type', 'identity')
      ->setLabel(new TranslatableMarkup('Identity'));

    $fields['event'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Event'))
      ->setSetting('allowed_values', [
        IdentityEvents::POST_MERGE => new TranslatableMarkup('Post-Merge'),
        IdentityEvents::PRE_ACQUISITION => new TranslatableMarkup('Pre-Acquisition'),
      ]);

    $fields['notification_url'] = BaseFieldDefinition::create('uri')
      ->setLabel(new TranslatableMarkup('Notification Url'))
      ->setDisplayOptions('view', [
        'type' => 'uri_link',
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
