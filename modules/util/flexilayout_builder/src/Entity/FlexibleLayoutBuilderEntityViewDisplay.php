<?php

namespace Drupal\flexilayout_builder\Entity;

use Drupal\Component\Plugin\Exception\MissingValueContextException;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\flexilayout_builder\Plugin\SectionStorage\ConfigurableContextSectionStorageTrait;
use Drupal\flexilayout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Section;

class FlexibleLayoutBuilderEntityViewDisplay extends LayoutBuilderEntityViewDisplay {

  /**
   * Prepare contexts for layout rendering.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   Array of contexts keyed name.
   */
  protected function getContextsForEntity(FieldableEntityInterface $entity) {
    $contexts = parent::getContextsForEntity($entity);
    $contexts += \Drupal::service('ctools.context_mapper')->getContextValues(
      $this->getThirdPartySetting('flexilayout_builder', 'static_context', [])
    );

    /** @var \Drupal\ctools\Plugin\RelationshipManager $relationship_manager */
    $relationship_manager = \Drupal::service('plugin.manager.ctools.relationship');
    /** @var \Drupal\Core\Plugin\Context\ContextHandler $context_handler */
    $context_handler = \Drupal::service('context.handler');

    foreach ($this->getThirdPartySetting('flexilayout_builder', 'relationships', []) as $machine_name => $relationship) {
      /** @var \Drupal\ctools\Plugin\RelationshipInterface $plugin */
      $plugin = $relationship_manager->createInstance($relationship['plugin'], $relationship['settings'] ?: []);
      $context_handler->applyContextMapping($plugin, $contexts);

      $contexts[$machine_name] = $plugin->getRelationship();
    }

    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  protected function addSectionField($entity_type_id, $bundle, $field_name) {
    parent::addSectionField($entity_type_id, $bundle, $field_name);

    // Add the layout settings field.
    $settings_field_name = OverridesSectionStorage::SETTINGS_FIELD_NAME;
    $field = FieldConfig::loadByName($entity_type_id, $bundle, $settings_field_name);
    if (!$field) {
      $field_storage = FieldStorageConfig::loadByName($entity_type_id, $settings_field_name);
      if (!$field_storage) {
        $field_storage = FieldStorageConfig::create([
          'entity_type' => $entity_type_id,
          'field_name' => $settings_field_name,
          'type' => 'layout_settings',
          'locked' => TRUE,
        ]);
        $field_storage->setTranslatable(FALSE);
        $field_storage->save();
      }

      $field = FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => $bundle,
        'label' => t('Layout Settings'),
      ]);
      $field->setTranslatable(FALSE);
      $field->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function removeSectionField($entity_type_id, $bundle, $field_name) {
    $query = $this->entityTypeManager()->getStorage($this->getEntityTypeId())->getQuery()
      ->condition('targetEntityType', $this->getTargetEntityTypeId())
      ->condition('bundle', $this->getTargetBundle())
      ->condition('mode', $this->getMode(), '<>')
      ->condition('third_party_settings.layout_builder.allow_custom', TRUE);
    $enabled = (bool) $query->count()->execute();
    if (!$enabled && $field = FieldConfig::loadByName($entity_type_id, $bundle, $field_name)) {
      $field->delete();
    }
    if (!$enabled && $field = FieldConfig::loadByName($entity_type_id, $bundle, OverridesSectionStorage::SETTINGS_FIELD_NAME)) {
      $field->delete();
    }
  }

}
