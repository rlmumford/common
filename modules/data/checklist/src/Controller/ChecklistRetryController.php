<?php

namespace Drupal\checklist\Controller;

use Drupal\checklist\Form\ChecklistItemRetryForm;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Opens the authorized retry confirmation in its checklist row.
 */
class ChecklistRetryController extends ControllerBase {

  /**
   * Builds the same confirmation for inline AJAX and standalone navigation.
   */
  public function view(Request $request, string $item_uuid, string $attempt_id): array|AjaxResponse {
    $form = $this->formBuilder()->getForm(ChecklistItemRetryForm::class, $item_uuid, $attempt_id);
    if ($request->query->get('_wrapper_format') !== 'drupal_ajax') {
      return $form;
    }
    $selector = '#checklist-retry-slot-' . $attempt_id;
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand($selector, $form));
    $response->addCommand(new InvokeCommand($selector . ' input:checked', 'focus'));
    return $response;
  }

}
