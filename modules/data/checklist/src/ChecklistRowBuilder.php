<?php

namespace Drupal\checklist;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Attempt\ChecklistAttemptJournal;
use Drupal\checklist\Plugin\ChecklistItemHandler\IterativeChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ActionStateChecklistItemHandlerInterface;
use Drupal\checklist\Form\ChecklistItemRowForm;
use Drupal\checklist\Form\ChecklistItemActionForm;
use Drupal\checklist\Plugin\ChecklistItemHandler\SimplyCheckableChecklistItemHandler;
use Drupal\checklist\PluginForm\CustomFormObjectClassInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\typed_data\PlaceholderResolverInterface;

/**
 * Builds the same checklist rows for initial display and AJAX reconciliation.
 */
class ChecklistRowBuilder {

  /**
   * Constructs the row builder.
   *
   * @param \Drupal\checklist\ChecklistContextPreparer $contextPreparer
   *   Prepares fresh runtime contexts.
   * @param \Drupal\checklist\ChecklistContextCollectorInterface $contextCollector
   *   Supplies placeholder contexts.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $classResolver
   *   Creates row form objects.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   Builds row controls.
   * @param \Drupal\typed_data\PlaceholderResolverInterface $placeholderResolver
   *   Resolves labels against current typed data.
   * @param \Drupal\checklist\Attempt\ChecklistAttemptJournal $journal
   *   Provides retry targets and current input-request attempt metadata.
   */
  public function __construct(
    protected ChecklistContextPreparer $contextPreparer,
    protected ChecklistContextCollectorInterface $contextCollector,
    protected ClassResolverInterface $classResolver,
    protected FormBuilderInterface $formBuilder,
    protected PlaceholderResolverInterface $placeholderResolver,
    protected ChecklistAttemptJournal $journal,
  ) {}

  /**
   * Builds a visible row, or returns NULL when the item is hidden.
   */
  public function build(ChecklistInterface $checklist, ChecklistItemInterface $checklist_item, ?string $langcode = NULL): ?array {
    if (!$checklist_item->access('view action state')) {
      return NULL;
    }
    $name = $checklist_item->getName();
    $id = $checklist->getEntity()->getEntityTypeId() . '--' . str_replace(':', '--', $checklist->getKey());
    $handler = $checklist_item->getHandler();

    $available = $this->contextPreparer->prepare($checklist, $checklist_item);
    $contexts = $this->contextCollector->collectRuntimeContexts($checklist);
    $placeholder_datas = [$checklist->getEntity()->getEntityTypeId() => EntityAdapter::createFromEntity($checklist->getEntity())];
    foreach ($contexts as $key => $context) {
      $placeholder_datas[$key] = $context->getContextData();
    }
    $placeholder_datas['checklist_item'] = EntityAdapter::createFromEntity($checklist_item);

    $state = [
      'visible' => TRUE,
      'contexts_available' => $available,
      'complete' => $checklist_item->isComplete(),
      'failed' => $checklist_item->isFailed(),
      'applicable' => $available && $checklist_item->isApplicable() === TRUE,
      'required' => !$available || $checklist_item->isRequired(),
      'actionable' => $checklist_item->isIncomplete() && $available && $checklist_item->isApplicable() === TRUE && $checklist_item->isActionable(),
    ];
    $checklist_item_classes = ['ci'];
    foreach (['complete', 'failed', 'applicable', 'required', 'actionable'] as $flag) {
      if ($state[$flag]) {
        $checklist_item_classes[] = 'ci-' . $flag;
      }
    }
    foreach (['applicable' => 'inapplicable', 'required' => 'optional', 'actionable' => 'inactionable'] as $flag => $inverse) {
      if (!$state[$flag]) {
        $checklist_item_classes[] = 'ci-' . $inverse;
      }
    }

    $controls = [];
    if ($available && $handler->hasFormClass('row')) {
      $form_class = ChecklistItemRowForm::class;
      if (is_subclass_of($handler->getFormClass('row'), CustomFormObjectClassInterface::class)) {
        $form_class = [$handler->getFormClass('row'), 'getFormObjectClass']($handler, $form_class);
      }

      /** @var \Drupal\checklist\Form\ChecklistItemRowForm $form_obj */
      $form_obj = $this->classResolver->getInstanceFromDefinition($form_class);
      $form_obj->setChecklistItem($checklist_item);
      $form_obj->setActionUrl(Url::fromRoute(
        'checklist.item.row_form',
        [
          'entity_type' => $checklist->getEntity()->getEntityTypeId(),
          'entity_id' => $checklist->getEntity()->id(),
          'checklist' => $checklist->getKey(),
          'item_name' => $checklist_item->getName(),
        ]
      ));
      $controls = $this->formBuilder->getForm($form_obj);
    }

    // @todo Estimates
    // @todo Icons
    $cache_metadata = new BubbleableMetadata();
    $row = [
      '#checklist_state' => $state,
      '#attributes' => [
        'class' => $checklist_item_classes,
        'data-is-complete' => $state['complete'] ? 'true' : 'false',
        'data-is-failed' => $state['failed'] ? 'true' : 'false',
        'data-is-actionable' => $state['actionable'] ? 'true' : 'false',
        'data-ciid' => $checklist_item->id(),
        'data-ciname' => $checklist_item->getName(),
      ],
      'checkbox' => $controls,
      'label' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->placeholderResolver->replacePlaceHolders(
          $checklist_item->title->value,
          $placeholder_datas,
          $cache_metadata,
          ['langcode' => $langcode]
        ),
        '#attributes' => [
          'class' => [
            'ci-label',
          ],
        ],
      ],
    ];
    if (!$checklist->getEntity()->isNew() && !$checklist_item->isNew()) {
      $row['history'] = [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('History'),
        '#url' => Url::fromRoute('checklist.item.history_page', [
          'entity_type' => $checklist->getEntity()->getEntityTypeId(),
          'entity_id' => $checklist->getEntity()->id(),
          'checklist' => $checklist->getKey(),
          'item_name' => $name,
        ]),
        '#attributes' => [
          'class' => ['use-ajax'],
          'aria-label' => new TranslatableMarkup('History for @item', ['@item' => $checklist_item->get('title')->value ?: $name]),
        ],
        '#attached' => ['library' => ['checklist/interactive_checklist']],
        '#wrapper_attributes' => ['class' => ['checklist-item-history-cell']],
      ];
    }
    if ($checklist_item->isFailed() && !$checklist_item->isNew() && !$checklist->getEntity()->isNew()
      && $handler instanceof IterativeChecklistItemHandlerInterface
      && $checklist_item->getMethod() === ChecklistItemInterface::METHOD_AUTO
      && $checklist_item->access('execute iteration')) {
      $attempt = $this->journal->latest($checklist_item);
      if ($attempt?->status === ChecklistAttempt::FAILED && $attempt->path === ChecklistAttempt::ACTION) {
        $row['retry'] = [
          '#type' => 'container',
          '#wrapper_attributes' => ['class' => ['checklist-item-retry-cell']],
          'message' => ['#plain_text' => new TranslatableMarkup('This item failed.')],
          'editor' => [
            '#type' => 'container',
            '#weight' => 10,
            '#attributes' => [
              'id' => 'checklist-retry-slot-' . $attempt->id,
              'class' => ['checklist-retry-slot'],
            ],
          ],
          'link' => [
            '#type' => 'link',
            '#title' => new TranslatableMarkup('Retry…'),
            '#url' => Url::fromRoute('checklist.item.retry', [
              'item_uuid' => $checklist_item->uuid(),
              'attempt_id' => $attempt->id,
            ]),
            '#attributes' => [
              'class' => ['use-ajax'],
            ],
            '#attached' => ['library' => ['checklist/interactive_checklist']],
          ],
        ];
      }
    }
    $input_required = FALSE;
    if ($available && $handler instanceof ActionStateChecklistItemHandlerInterface && ($progress = $handler->getActionState())) {
      // Retained input state may survive failure or a newly queued retry. Only
      // a waiting attempt can accept it; keep polling queued retries instead.
      if ($progress->inputRequired && $handler instanceof IterativeChecklistItemHandlerInterface && !$checklist_item->isNew()) {
        $input_attempt = $attempt ?? $this->journal->latest($checklist_item);
        if ($input_attempt?->status !== ChecklistAttempt::WAITING) {
          $progress = new ChecklistActionState(
            $progress->stage,
            match ($input_attempt?->status) {
              ChecklistAttempt::QUEUED => new TranslatableMarkup('Waiting to run.'),
              ChecklistAttempt::RUNNING => new TranslatableMarkup('Processing…'),
              default => new TranslatableMarkup('Automatic processing stopped.'),
            },
            $progress->completed,
            $progress->total,
            $progress->updatedAt,
          );
        }
      }
      $input_required = $progress->inputRequired;
      $progress_build = $this->buildProgress($progress);
      if ($progress_build) {
        $row['progress'] = $progress_build;
      }
    }
    $cache_metadata->applyTo($row);

    if ($checklist_item->getHandler() instanceof SimplyCheckableChecklistItemHandler) {
      $row['checkbox']['#attributes']['class'][] = 'checklist-checkbox-checkable';
      $row['#attributes']['class'][] = 'checklist-item-checkable';
    }
    if ($checklist_item->getHandler()->hasFormClass('action')) {
      $row['#attributes']['class'][] = 'checklist-item-has-form';

      $row['action_form'] = [
        '#wrapper_attributes' => [
          'class' => ['action-form-container'],
          'id' => $id . '--' . $name . '--action-form-container',
        ],
      ];
    }
    if ($checklist_item->getMethod() === ChecklistItemInterface::METHOD_AUTO) {
      $row['#attributes']['data-input-required'] = $input_required ? 'true' : 'false';
      $row['#attributes']['data-refresh-progress'] = $state['actionable'] && !$input_required && $handler instanceof ActionStateChecklistItemHandlerInterface ? 'true' : 'false';
      if ($input_required && $state['actionable'] && $handler->hasFormClass('action') && $checklist_item->access('execute action operation')) {
        $form_class = ChecklistItemActionForm::class;
        if (is_subclass_of($handler->getFormClass('action'), CustomFormObjectClassInterface::class)) {
          $form_class = [$handler->getFormClass('action'), 'getFormObjectClass']($handler, $form_class);
        }
        $form = $this->classResolver->getInstanceFromDefinition($form_class);
        $form->setChecklistItem($checklist_item);
        $form->setActionUrl(Url::fromRoute('checklist.item.action_form', [
          'entity_type' => $checklist->getEntity()->getEntityTypeId(),
          'entity_id' => $checklist->getEntity()->id(),
          'checklist' => $checklist->getKey(),
          'item_name' => $name,
        ]));
        $row['action_form']['input'] = $this->formBuilder->getForm($form);
      }
    }
    return $row;
  }

  /**
   * Renders only the handler's public progress projection, never raw state.
   */
  protected function buildProgress(ChecklistActionState $progress): array {
    if (($progress->message === NULL || $progress->message === '') && $progress->completed === NULL && !$progress->inputRequired) {
      return [];
    }
    $build = [
      '#type' => 'container',
      '#wrapper_attributes' => ['class' => ['checklist-item-progress-cell']],
      '#attributes' => [
        'class' => ['checklist-item-progress'],
        'role' => 'status',
        'aria-live' => 'polite',
        'aria-atomic' => 'true',
      ],
    ];
    if ($progress->message !== NULL && $progress->message !== '') {
      $build['message'] = ['#plain_text' => $progress->message];
    }
    if ($progress->completed !== NULL && $progress->total !== NULL && $progress->total > 0) {
      $build['meter'] = [
        '#type' => 'html_tag',
        '#tag' => 'progress',
        '#attributes' => [
          'value' => $progress->completed,
          'max' => $progress->total,
          'aria-label' => new TranslatableMarkup('Item progress'),
        ],
      ];
      $build['count'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => new TranslatableMarkup('@completed of @total', [
          '@completed' => $progress->completed,
          '@total' => $progress->total,
        ]),
      ];
    }
    elseif ($progress->completed !== NULL) {
      $build['count'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => new TranslatableMarkup('@completed completed', ['@completed' => $progress->completed]),
      ];
    }
    if ($progress->inputRequired) {
      $build['input_required'] = [
        '#type' => 'html_tag',
        '#tag' => 'strong',
        '#value' => new TranslatableMarkup('Input required'),
      ];
    }
    // Machine stage names and timestamps are not translated display messages.
    return $build;
  }

}
