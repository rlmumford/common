<?php

namespace Drupal\identity_ui\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\Element;

/**
 * Format for rendering identity data in a table.
 *
 * @FieldFormatter(
 *   id = "identity_data_reference_table",
 *   label = @Translation("Rendered data table"),
 *   description = @Translation("Display the referenced entities rendered by entity_view() compacted into a table."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 *
 * @package Drupal\identity_ui\Plugin\Field\FieldFormatter
 */
class IdentityDataReferenceTableFormatter extends EntityReferenceEntityFormatter {

  /**
   * {@inheritdoc}
   */
  public function view(FieldItemListInterface $items, $langcode = NULL) {
    // Default the language to the current content language.
    if (empty($langcode)) {
      $langcode = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    }
    $tables = $this->viewElements($items, $langcode);

    return $tables + [
      '#type' => 'details',
      '#title' => $this->fieldDefinition->getLabel(),
      '#open' => !empty(Element::children($tables)),
      '#view_mode' => $this->viewMode,
      '#language' => $items->getLangcode(),
      '#field_name' => $this->fieldDefinition->getName(),
      '#field_type' => $this->fieldDefinition->getType(),
      '#field_translatable' => $this->fieldDefinition->isTranslatable(),
      '#entity_type' => $items->getEntity()->getEntityTypeId(),
      '#bundle' => $items->getEntity()->bundle(),
      '#object' => $items->getEntity(),
      '#items' => $items,
      '#formatter' => $this->getPluginId(),
      '#attributes' => [
        'class' => [
          'field',
          'field--name-' . Html::cleanCssIdentifier($this->fieldDefinition->getName()),
          'field--type-' . Html::cleanCssIdentifier($this->fieldDefinition->getType()),
          'field--formatter-' . Html::cleanCssIdentifier($this->getPluginId()),
        ]
      ]
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $cacheable_metadata = new CacheableMetadata();
    $view_mode = $this->getSetting('view_mode');

    $tables = [];
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
      $display = $this->entityDisplayRepository->getViewDisplay($entity->getEntityTypeId(), $entity->bundle(), $view_mode);
      $components = $display->getComponents();
      uasort($components, [SortArray::class, 'sortByWeightElement']);

      if (!isset($tables[$entity->bundle()])) {
        $header = [];
        foreach ($components as $name => $component) {
          if ($definition = $entity->getFieldDefinition($name)) {
            $header[$name] = $definition->getLabel();
          }
        }

        $tables[$entity->bundle()] = [
          '#theme' => 'table',
          '#rows' => [],
          '#header' => $header,
        ];
      }

      $row = [];
      foreach ($components as $name => $component) {
        /** @var \Drupal\Core\Field\FormatterInterface $formatter */
        if ($formatter = $display->getRenderer($name)) {
          $items = $entity->get($name);
          $items->filterEmptyItems();

          $formatter->prepareView([
            $entity->id() => $items,
          ]);

          /** @var \Drupal\Core\Access\AccessResultInterface $field_access */
          $field_access = $items->access('view', NULL, TRUE);
          $row[$name]['data'] = $field_access->isAllowed() ? ['#label_display' => 'hidden'] + $formatter->view($items, $langcode) : [];

          $cacheable_metadata->addCacheableDependency($field_access);
        }
      }

      $tables[$entity->bundle()]['#rows'][] = $row;
    }

    // Now go through and remove any columns that have no data at all.
    foreach ($tables as $bundle => $table) {
      foreach ($table['#header'] as $component_name => $label) {
        $has_content = FALSE;
        foreach ($table['#rows'] as $row) {
          if (!empty(array_filter($row[$component_name]))) {
            $has_content = TRUE;
            break 1;
          }
        }

        if (!$has_content) {
          unset($tables[$bundle]['#header'][$component_name]);
          foreach ($table['#rows'] as $key => $row) {
            unset($tables[$bundle]['#rows'][$key]);
          }
        }
      }
    }

    $cacheable_metadata->applyTo($tables);
    return $tables;
  }

}
