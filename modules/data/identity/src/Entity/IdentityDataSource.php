<?php

namespace Drupal\identity\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Entity class for Identities.
 *
 * @ContentEntityType(
 *   id = "identity_data_source",
 *   label = @Translation("Identity Data Source"),
 *   label_singular = @Translation("identity data source"),
 *   label_plural = @Translation("identity data sources"),
 *   label_count = @PluralTranslation(
 *     singular = "@count identity data source",
 *     plural = "@count identity data sources"
 *   ),
 *   bundle_label = @Translation("IdentityDataSource Type"),
 *   handlers = {
 *     "storage" = "Drupal\identity\Entity\IdentityDataSourceStorage",
 *     "access" = "Drupal\identity\Entity\IdentityDataSourceAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "identity_data_source",
 *   revision_table = "identity_data_source_revision",
 *   admin_permission = "administer identities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "uuid" = "uuid",
 *   },
 * )
 */
class IdentityDataSource extends ContentEntityBase implements IdentityDataSourceInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRevisionable(TRUE)
      ->setDefaultValueCallback('\Drupal\identity\Entity\IdentityDataSource::createLabel')
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }
}

