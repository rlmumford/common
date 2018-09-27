<?php

namespace Drupal\service\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\service\ServiceInterface;
use Drupal\service\Entity\ServiceType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Entity class for Services.
 *
 * @ContentEntityType(
 *   id = "service",
 *   label = @Translation("Service"),
 *   label_singular = @Translation("service"),
 *   label_plural = @Translation("services"),
 *   label_count = @PluralTranslation(
 *     singular = "@count service",
 *     plural = "@count services"
 *   ),
 *   bundle_label = @Translation("Service Type"),
 *   handlers = {
 *     "list_builder" = "Drupal\service\ServiceListBuilder",
 *     "storage" = "Drupal\service\ServiceStorage",
 *     "access" = "Drupal\service\ServiceAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\service\Form\ServiceForm"
 *     },
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "service",
 *   revision_table = "service_revision",
 *   admin_permission = "administer services",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *   },
 *   bundle_entity_type = "service_type",
 *   field_ui_base_route = "entity.service_type.edit_form",
 *   links = {
 *     "collection" = "/service",
 *     "canonical" = "/service/{service}",
 *     "edit-form" = "/service/{service}/edit"
 *   }
 * )
 */
class Service extends ContentEntityBase implements ServiceInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['state'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Active?'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['creator'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Creator'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback('\Drupal\service\Entity\Service::getCurrentUserId')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'timestamp',
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['service'] = BaseFieldDefinition::create('service_reference')
      ->setLabel(t('Service'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'service')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['manager'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Manager'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback('\Drupal\service\Entity\Service::getCurrentUserId')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['recipients'] = BaseFieldDefinition::create('entity_reference')
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setLabel(t('Recipients'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return ServiceType::load($this->bundle());
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    $type = $this->getType();
    if ($type->get('default_label')) {
      $this->label->value = $this->applyTokens($type->get('default_label'));
    }
  }

  /**
   * Apply tokens to a string.
   */
  protected function applyTokens($string, BubbleableMetadata $bubbleable_metadata = NULL) {
    $token_service = \Drupal::token();

    return $token_service->replace($string, ['service' => $this], [], $bubbleable_metadata);
  }
}

