<?php

namespace Drupal\checklist;

use Drupal\checklist\Ajax\UpdateItemStateCommand;
use Drupal\checklist\Form\ChecklistItemRowForm;
use Drupal\checklist\PluginForm\CustomFormObjectClassInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;

/**
 * Refreshes controls and readiness for rows already present in a checklist.
 */
class ChecklistRowUpdater {

  /**
   * Constructs the row updater.
   *
   * @param \Drupal\checklist\ChecklistContextPreparer $contextPreparer
   *   Prepares current handler contexts.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $classResolver
   *   Creates row form objects.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   Builds current row controls.
   */
  public function __construct(
    protected ChecklistContextPreparer $contextPreparer,
    protected ClassResolverInterface $classResolver,
    protected FormBuilderInterface $formBuilder,
  ) {}

  /**
   * Refreshes rows without rebuilding active action forms or executing items.
   */
  public function refresh(AjaxResponse $response, ChecklistInterface $checklist): void {
    foreach ($checklist->getOrderedItems() as $item) {
      if (!$item->access('view action state')) {
        $response->addCommand(new UpdateItemStateCommand($item, ['visible' => FALSE]));
        continue;
      }
      $available = $this->contextPreparer->prepare($checklist, $item);
      $applicable = $available && $item->isApplicable() === TRUE;
      $state = [
        'visible' => TRUE,
        'contexts_available' => $available,
        'complete' => $item->isComplete(),
        'failed' => $item->isFailed(),
        'applicable' => $applicable,
        'required' => !$available || $item->isRequired(),
        'actionable' => $item->isIncomplete() && $applicable && $item->isActionable(),
      ];
      $handler = $item->getHandler();
      if ($available && $handler->hasFormClass('row')) {
        $form_class = ChecklistItemRowForm::class;
        if (is_subclass_of($handler->getFormClass('row'), CustomFormObjectClassInterface::class)) {
          $form_class = [$handler->getFormClass('row'), 'getFormObjectClass']($handler, $form_class);
        }
        $form_object = $this->classResolver->getInstanceFromDefinition($form_class);
        $form_object->setChecklistItem($item);
        $form_object->setActionUrl(Url::fromRoute('checklist.item.row_form', [
          'entity_type' => $checklist->getEntity()->getEntityTypeId(),
          'entity_id' => $checklist->getEntity()->id(),
          'checklist' => $checklist->getKey(),
          'item_name' => $item->getName(),
        ]));
        $form = $this->formBuilder->getForm($form_object);
        $response->addCommand(new ReplaceCommand('#' . $form['#wrapper_id'], $form));
      }
      $response->addCommand(new UpdateItemStateCommand($item, $state));
    }
  }

}
