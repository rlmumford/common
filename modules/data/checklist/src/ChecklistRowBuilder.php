<?php

namespace Drupal\checklist;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Form\ChecklistItemRowForm;
use Drupal\checklist\Plugin\ChecklistItemHandler\SimplyCheckableChecklistItemHandler;
use Drupal\checklist\PluginForm\CustomFormObjectClassInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\BubbleableMetadata;
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
   */
  public function __construct(
    protected ChecklistContextPreparer $contextPreparer,
    protected ChecklistContextCollectorInterface $contextCollector,
    protected ClassResolverInterface $classResolver,
    protected FormBuilderInterface $formBuilder,
    protected PlaceholderResolverInterface $placeholderResolver,
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
    return $row;
  }

}
