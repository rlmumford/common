<?php

namespace Drupal\checklist_entity_template\Form;

use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Form\ChecklistItemActionForm;
use Drupal\checklist_entity_template\TemplateEditor;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\flexiform\Api\InvalidInputException;
use Drupal\flexiform\Session\HtmlFormAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A thin HTML adapter over the same revisioned checklist action operations.
 */
class TemplateEditorForm extends ChecklistItemActionForm {

  /**
   * The shared checklist operation coordinator.
   */
  protected TemplateEditor $editor;

  /**
   * Converts the shared schema to browser controls.
   */
  protected HtmlFormAdapter $html;

  /**
   * Reloads completed items for checklist AJAX refresh.
   */
  protected EntityTypeManagerInterface $entityTypes;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->editor = $container->get('checklist_entity_template.editor');
    $instance->html = new HtmlFormAdapter();
    $instance->entityTypes = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $description = $this->editor->describe($this->item);
    $wrapper = 'checklist-template-' . $this->item->uuid();
    $form['#prefix'] = '<div id="' . $wrapper . '">';
    $form['#suffix'] = '</div>';
    $form['#cache']['max-age'] = 0;
    $form['#editor_revision'] = $description['revision'];
    if ($url = $this->getActionUrl()) {
      $form['#action'] = $url->toString();
    }
    if ($description['status'] === 'ready') {
      $form['editor'] = $this->html->build($description, ['editor']);
    }
    elseif (in_array($description['status'], ['new', 'preparing'], TRUE)) {
      if ($description['status'] === 'preparing') {
        $form['preparing'] = [
          '#type' => 'item',
          '#title' => $this->t('Preparing your form'),
          '#markup' => $this->t('Please wait while the entity is prepared.'),
        ];
        $form['progress'] = [
          '#type' => 'html_tag',
          '#tag' => 'progress',
          '#attributes' => ['aria-label' => $this->t('Preparing your form')],
        ];
      }
      $form['actions']['progress'] = [
        '#type' => 'submit',
        '#value' => $description['status'] === 'new' ? $this->t('Open form') : $this->t('Check progress'),
        '#editor_operation' => $description['status'] === 'new' ? 'start' : 'advance',
        '#attributes' => ['data-flexiform-advance' => $description['status']],
      ];
      $form['#attached']['library'][] = 'flexiform/preparation';
    }
    else {
      $form['status'] = [
        '#plain_text' => match ($description['status']) {
        'complete' => $this->t('Entity created.'),
        'failed' => $this->t('This attempt failed. Working data has been retained for review.'),
        default => $this->t('An operation is in progress. Refresh to check its status.'),
        },
      ];
    }
    $this->buttons($form, $wrapper);
    $form['#process'][] = '::processSetAjaxActionUrls';
    return $form;
  }

  /**
   * Uses the host's protected submission and refresh for every shared action.
   */
  protected function buttons(array &$element, string $wrapper): void {
    if (($element['#type'] ?? '') === 'submit') {
      $element['#submit'] = ['::submitForm'];
      $element['#ajax'] = ['callback' => '::refreshEditor', 'wrapper' => $wrapper, 'progress' => ['type' => 'none']];
    }
    foreach ($element as $key => &$child) {
      if (is_array($child) && !str_starts_with((string) $key, '#')) {
        $this->buttons($child, $wrapper);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    if (!isset($button['#flexiform_action'])) {
      return;
    }
    try {
      $input = $this->html->input($form_state);
      $this->editor->validateInput($this->item, $form['#editor_revision'], $button['#flexiform_action'], $input);
      $form_state->set('editor_input', $input);
    }
    catch (InvalidInputException $exception) {
      foreach ($exception->errors as $name => $messages) {
        $form_state->setErrorByName('editor][values][' . $name, implode(' ', $messages));
      }
    }
    catch (ChecklistAttemptConflictException $exception) {
      $form_state->setErrorByName('editor', $exception->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $operation = $button['#editor_operation'] ?? 'form/' . $button['#flexiform_action'];
    $parameters = ['revision' => $form['#editor_revision']];
    if (isset($button['#flexiform_action'])) {
      $parameters['input'] = $form_state->get('editor_input');
    }
    try {
      $description = $this->editor->operate($this->item, $operation, $parameters, ChecklistAttempt::ACTION_FORM);
      $form_state->set('editor_complete', $description['status'] === 'complete');
    }
    catch (ChecklistAttemptConflictException | InvalidInputException $exception) {
      $this->messenger()->addError($exception->getMessage());
    }
    $this->item = $this->entityTypes->getStorage('checklist_item')->loadUnchanged($this->item->id());
    $form_state->setRebuild();
    // Rebuild from authoritative values; never replay the previous page's
    // submitted values over a newer API edit or wizard navigation result.
    $input = $form_state->getUserInput();
    unset($input['editor']);
    $form_state->setUserInput($input);
  }

  /**
   * Rebuilds from the shared instance after preparation or an editing action.
   */
  public function refreshEditor(array &$form, FormStateInterface $form_state): array|AjaxResponse {
    if ($form_state->get('editor_complete')) {
      $form_state->setRebuild(FALSE);
      return $this->onCompleteAjaxCallback($form, $form_state);
    }
    return $form;
  }

}
