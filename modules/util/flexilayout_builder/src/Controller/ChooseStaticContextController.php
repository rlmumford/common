<?php

namespace Drupal\flexilayout_builder\Controller;

use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\Core\Url;
use Drupal\flexilayout_builder\Plugin\SectionStorage\DisplayWideConfigSectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ChooseStaticContextController implements ContainerInjectionInterface {
  use AjaxHelperTrait;
  use StringTranslationTrait;

  /**
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('typed_data_manager')
    );
  }

  /**
   * ChooseStaticContextController constructor.
   *
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typed_data_manager
   */
  public function __construct(TypedDataManagerInterface $typed_data_manager) {
    $this->typedDataManager = $typed_data_manager;
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
    $build['#title'] = $this->t('Choose a data type');
    $build['#type'] = 'container';

    $definitions = $this->typedDataManager->getDefinitions();
    foreach ($definitions as $data_type => $definition) {
      list($category,) = explode(':', $data_type, 2);
      if ($category == $data_type) {
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
        'url' => Url::fromRoute('flexilayout_builder.add_static_context', [
          'section_storage_type' => $section_storage->getStorageType(),
          'section_storage' => $section_storage->getStorageId(),
          'data_type' => $data_type,
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
