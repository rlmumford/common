<?php

namespace Drupal\flexilayout_builder\Controller;

use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\Core\Url;
use Drupal\ctools\Plugin\RelationshipManagerInterface;
use Drupal\flexilayout_builder\Plugin\SectionStorage\DisplayWideConfigSectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ChooseRelationshipController implements ContainerInjectionInterface {
  use AjaxHelperTrait;
  use StringTranslationTrait;

  /**
   * @var \Drupal\ctools\Plugin\RelationshipManagerInterface
   */
  protected $relationshipManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.ctools.relationship')
    );
  }

  /**
   * ChooseStaticContextController constructor.
   *
   * @param \Drupal\ctools\Plugin\RelationshipManagerInterface $relationship_manager
   */
  public function __construct(RelationshipManagerInterface $relationship_manager) {
    $this->relationshipManager = $relationship_manager;
  }

  /**
   * Provides the UI for choosing a new static context.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return array
   *   A render array.
   */
  public function build(DisplayWideConfigSectionStorageInterface $section_storage) {
    $build['#title'] = $this->t('Choose a relationship');
    $build['#type'] = 'container';

    $definitions = $this->relationshipManager->getDefinitionsForContexts($section_storage->getContexts());
    foreach ($definitions as $plugin_id => $definition) {
      list($category,) = explode(':', $plugin_id, 2);
      if ($category == $plugin_id) {
        if (!empty($definition['deriver'])) {
          continue;
        }
        else {
          $category = 'other';
        }
      }

      if (!isset($definitions[$category])) {
        $category = 'other';
      }

      if (empty($build[$category])) {
        $build[$category] = [
          '#type' => 'details',
          '#title' => isset($definitions[$category]) ? $definitions[$category]['label'] : new TranslatableMarkup('Other'),
          'links' => [
            '#theme' => 'links',
          ]
        ];
      }

      $link = [
        'title' => $definition['label'],
        'url' => Url::fromRoute('flexilayout_builder.add_relationship', [
          'section_storage_type' => $section_storage->getStorageType(),
          'section_storage' => $section_storage->getStorageId(),
          'plugin' => $plugin_id,
        ]),
      ];
      if ($this->isAjax()) {
        $link['attributes']['class'][] = 'use-ajax';
        $link['attributes']['data-dialog-type'][] = 'dialog';
        $link['attributes']['data-dialog-renderer'][] = 'off_canvas';
      }
      $build[$category]['links']['#links'][] = $link;
    }

    if (isset($build['other'])) {
      $build['other']['#weight'] = 100;
    }

    return $build;
  }
}
