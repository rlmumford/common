<?php

namespace Drupal\checklist\Form;

use Drupal\checklist\Ajax\StartNextItemCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Action form for checklist items.
 */
class ChecklistItemActionForm extends ChecklistItemFormBase {

  /**
   * {@inheritdoc}
   */
  protected $formClass = 'action';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ci_' . $this->item->checklist->checklist->getKey() . '__' . $this->item->getName() . '_action_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    return 'ci_action_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $wrapper_id = "checklist-action--" . $this->item->checklist->checklist->getKey() . "--" . $this->item->getName();
    $form['#prefix'] = '<div id="' . $wrapper_id . '" class="checklist-item-action-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['complete'] = [
      '#type' => 'submit',
      '#value' => new TranslatableMarkup('Complete @name', ['@name' => $this->item->getName()]),
      '#name' => 'action_form_complete',
      '#submit' => [
        '::submitForm',
      ],
      '#ajax' => [
        'callback' => '::onCompleteAjaxCallback',
        'wrapper' => $wrapper_id,
      ],
    ];

    $form['#process'][] = '::processSetAjaxActionUrls';

    return parent::buildForm($form, $form_state);
  }

  /**
   * Traverse the form/element to correct any ajax urls.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The corrected element.
   */
  public function processSetAjaxActionUrls(array $element, FormStateInterface $form_state) {
    if ($form_state->getFormObject() instanceof ChecklistItemFormBase && ($url = $form_state->getFormObject()->getActionUrl())) {
      $ajax_url = clone $url;
      $options = $ajax_url->getOptions();
      $options['query'][FormBuilderInterface::AJAX_FORM_REQUEST] = TRUE;
      $ajax_url->setOptions($options);

      $this->prepareAllAjaxSettings($element, $ajax_url);
    }

    return $element;
  }

  /**
   * Ajax callback on completion.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|array
   *   The form array or an ajax response.
   */
  public function onCompleteAjaxCallback(array $form, FormStateInterface $form_state) {
    if (!$form_state->isExecuted() || $form_state->isRebuilding()) {
      return $form;
    }

    $response = new AjaxResponse();

    // First, clear the action form.
    /** @var \Drupal\checklist\ChecklistInterface $checklist */
    $checklist = $this->item->checklist->checklist;
    $form_container_id = $checklist->getEntity()->getEntityTypeId()
      . '--' . str_replace(':', '--', $checklist->getKey())
      . '--' . $this->item->getName()
      . '--action-form-container';
    $response->addCommand(new InsertCommand('#' . $form_container_id, '<div id="' . $form_container_id . '"></div>'));

    if ($this->item->isComplete()) {
      $response->addCommand(new StartNextItemCommand($this->item));
    }

    return $response;
  }

}
