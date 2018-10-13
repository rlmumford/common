<?php

namespace Drupal\flexilayout_builder\Controller;

use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\Core\Url;
use Drupal\flexilayout_builder\Plugin\SectionStorage\DisplayWideConfigSectionStorageInterface;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ViewContextController implements ContainerInjectionInterface {
  use AjaxHelperTrait;
  use LayoutBuilderContextTrait;
  use StringTranslationTrait;

  public function __construct(ContextRepositoryInterface $context_repository) {
    $this->contextRepository = $context_repository;
  }

  /**
   * Provides the UI for listing contexts.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return array
   *   A render array.
   */
  public function build(DisplayWideConfigSectionStorageInterface $section_storage) {
    $build['#title'] = $this->t('Available Contexts');
    $build['#type'] = 'container';

    $build['provided'] = [
      '#type' => 'details',
      '#title' => new TranslatableMarkup('Provided Contexts'),
      'table' => [
        '#type' => 'table',
        '#header' => [
          new TranslatableMarkup('Context'),
          new TranslatableMarkup('Name'),
          new TranslatableMarkup('Type'),
        ],
      ],
    ];

    $provided_contexts = $this->getAvailableContexts($section_storage);
    $static_contexts = $section_storage->getConfig('static_context') ?: [];
    $relationships = $section_storage->getConfig('relationships') ?: [];
    $provided_contexts = array_diff_key($provided_contexts, $static_contexts, $relationships);
    foreach ($provided_contexts as $machine_name => $context) {
      /** @var \Drupal\Core\Plugin\Context\ContextInterface $context */
      $row = [
        'context' => [
          '#type' => 'markup',
          '#markup' => $context->getContextDefinition()->getLabel(),
        ],
        'name' => [
          '#type' => 'markup',
          '#markup' => $machine_name,
        ],
        'type' => [
          '#type' => 'markup',
          '#markup' => $context->getContextDefinition()->getDataType(),
        ],
      ];
      $build['provided']['table'][$machine_name] = $row;
    }

    $build['static'] = [
      '#type' => 'details',
      '#title' => new TranslatableMarkup('Static Context'),
      'add_button' => [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Add Static Context'),
        '#url' => Url::fromRoute('flexilayout_builder.choose_static_context', [
          'section_storage_type' => $section_storage->getStorageType(),
          'section_storage' => $section_storage->getStorageId(),
        ])
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          new TranslatableMarkup('Context'),
          new TranslatableMarkup('Name'),
          new TranslatableMarkup('Type'),
          new TranslatableMarkup('Operations'),
        ],
      ],
    ];
    if ($this->isAjax()) {
      $build['static']['add_button']['#attributes']['class'][] = 'use-ajax';
      $build['static']['add_button']['#attributes']['data-dialog-type'][] = 'dialog';
      $build['static']['add_button']['#attributes']['data-dialog-renderer'][] = 'off_canvas';
    }
    foreach ($static_contexts as $machine_name => $context) {
      $links = [];
      $links['edit'] = [
        'title' => new TranslatableMarkup('Edit'),
        'url' => Url::fromRoute('flexilayout_builder.edit_static_context', [
          'section_storage_type' => $section_storage->getStorageType(),
          'section_storage' => $section_storage->getStorageId(),
          'machine_name' => $machine_name,
        ]),
      ];

      if ($this->isAjax()) {
        $links['edit']['attributes']['class'][] = 'use-ajax';
        $links['edit']['attributes']['data-dialog-type'][] = 'dialog';
        $links['edit']['attributes']['data-dialog-renderer'][] = 'off_canvas';
      }

      $row = [
        'context' => [
          '#markup' => $context['label'],
        ],
        'name' => [
          '#markup' => $machine_name,
        ],
        'type' => [
          '#markup' => $context['type'],
        ],
        'operations' => [
          '#type' => 'operations',
          '#links' => $links,
        ]
      ];
      $build['static']['table'][$machine_name] = $row;
    }

    $build['relationships'] = [
      '#type' => 'details',
      '#title' => new TranslatableMarkup('Related Context'),
      'add_button' => [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Add Related Context'),
        '#url' => Url::fromRoute('flexilayout_builder.choose_relationship', [
          'section_storage_type' => $section_storage->getStorageType(),
          'section_storage' => $section_storage->getStorageId(),
        ])
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          new TranslatableMarkup('Relationship'),
          new TranslatableMarkup('Name'),
          new TranslatableMarkup('Type'),
          new TranslatableMarkup('Operations'),
        ],
      ],
    ];
    if ($this->isAjax()) {
      $build['relationships']['add_button']['#attributes']['class'][] = 'use-ajax';
      $build['relationships']['add_button']['#attributes']['data-dialog-type'][] = 'dialog';
      $build['relationships']['add_button']['#attributes']['data-dialog-renderer'][] = 'off_canvas';
    }
    foreach ($relationships as $machine_name => $context) {
      $links = [];
      $links['edit'] = [
        'title' => new TranslatableMarkup('Edit'),
        'url' => Url::fromRoute('flexilayout_builder.edit_relationship', [
          'section_storage_type' => $section_storage->getStorageType(),
          'section_storage' => $section_storage->getStorageId(),
          'machine_name' => $machine_name,
        ]),
      ];

      if ($this->isAjax()) {
        $links['edit']['attributes']['class'][] = 'use-ajax';
        $links['edit']['attributes']['data-dialog-type'][] = 'dialog';
        $links['edit']['attributes']['data-dialog-renderer'][] = 'off_canvas';
      }

      $row = [
        'context' => [
          '#markup' => $context['label'],
        ],
        'name' => [
          '#markup' => $machine_name,
        ],
        'type' => [
          '#markup' => $context['type'],
        ],
        'operations' => [
          '#type' => 'operations',
          '#links' => $links,
        ]
      ];
      $build['relationships']['table'][$machine_name] = $row;
    }

    // @todo: Relationships

    return $build;
  }

  /**
   * Instantiates a new instance of this class.
   *
   * This is a factory method that returns a new instance of this class. The
   * factory should pass any needed dependencies into the constructor of this
   * class, but not the container itself. Every call to this method must return
   * a new instance of this class; that is, it may not implement a singleton.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container this instance should use.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('context.repository')
    );
  }
}
