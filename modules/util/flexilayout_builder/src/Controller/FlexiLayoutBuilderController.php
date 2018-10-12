<?php

namespace Drupal\flexilayout_builder\Controller;

use Drupal\Core\Url;
use Drupal\layout_builder\Controller\LayoutBuilderController;
use Drupal\layout_builder\SectionStorageInterface;

class FlexiLayoutBuilderController extends LayoutBuilderController {

  /**
   * {@inheritdoc}
   */
  public function layout(SectionStorageInterface $section_storage, $is_rebuilding = FALSE) {
    $build = parent::layout($section_storage, $is_rebuilding);

    $build['manage_context'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage Available Context'),
      '#url' => Url::fromRoute('flexilayout_builder.view_context', [
        'section_storage_type' => $section_storage->getStorageType(),
        'section_storage' => $section_storage->getStorageId(),
      ]),
      '#attributes' => [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'dialog',
        'data-dialog-renderer' => 'off_canvas',
      ],
    ];

    return $build;
  }
}
