<?php

namespace Drupal\checklist\Form;

use Drupal\checklist\ChecklistActionResourcePaneUpdater;
use Drupal\Core\Form\FormAjaxResponseBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Refreshes resources after any checklist item form AJAX callback.
 */
class ChecklistFormAjaxResponseBuilder implements FormAjaxResponseBuilderInterface {

  /**
   * Constructs the response builder decorator.
   *
   * @param \Drupal\Core\Form\FormAjaxResponseBuilderInterface $inner
   *   The original form response builder.
   * @param \Drupal\checklist\ChecklistActionResourcePaneUpdater $resourcePaneUpdater
   *   Rebuilds resources with current contexts and access checks.
   */
  public function __construct(
    protected FormAjaxResponseBuilderInterface $inner,
    protected ChecklistActionResourcePaneUpdater $resourcePaneUpdater,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildResponse(Request $request, array $form, FormStateInterface $form_state, array $commands) {
    // Core preserves callback commands, attachments and form build ID changes.
    // Read the item afterwards: a callback can replace the working checklist.
    $response = $this->inner->buildResponse($request, $form, $form_state, $commands);
    $form_object = $form_state->getFormObject();
    if ($form_object instanceof ChecklistItemFormBase && ($item = $form_object->getChecklistItem())) {
      $this->resourcePaneUpdater->refresh($response, $item->checklist->checklist);
    }
    return $response;
  }

}
