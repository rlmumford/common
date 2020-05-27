<?php

namespace Drupal\checklist\PluginForm;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Form\ChecklistRowForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

class SimplyCheckableItemRowForm extends PluginFormBase {

  /**
   * @var \Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface
   */
  protected $plugin;

  /**
   * Form constructor.
   *
   * Plugin forms are embedded in other forms. In order to know where the plugin
   * form is located in the parent form, #parents and #array_parents must be
   * known, but these are not available during the initial build phase. In order
   * to have these properties available when building the plugin form's
   * elements, let this method return a form element that has a #process
   * callback and build the rest of the form in the callback. By the time the
   * callback is executed, the element's #parents and #array_parents properties
   * will have been set by the form API. For more documentation on #parents and
   * #array_parents, see \Drupal\Core\Render\Element\FormElement.
   *
   * @param array $form
   *   An associative array containing the initial structure of the plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   *
   * @return array
   *   The form structure.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $item = $this->plugin->getItem();
    $is_reversible = !empty($this->plugin->getConfiguration()['reversible']);

    // @todo: Move into ChecklistRowForm somehow?
    $wrapper_id = "checklist-row--".$item->checklist->checklist->getKey()."--".$item->getName();
    $form['#prefix'] = '<div id="'.$wrapper_id.'" class="checklist-item-row-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['checkbox'] = [
      '#type' => 'checkbox',
      '#title' => new TranslatableMarkup('Complete?'),
      '#title_display' => 'invisible',
      '#default_value' => $item->isComplete(),
      '#disabled' => (!$is_reversible && $item->isComplete()) || $item->isFailed() || !$item->isActionable(),
      '#ajax' => [
        'callback' => [ChecklistRowForm::class, 'onCompleteAjaxCallback'],
        'wrapper' => $wrapper_id,
        'trigger_as' => ['name' => 'complete'],
      ]
    ];

    $form['complete'] = [
      '#type' => 'submit',
      '#value' => new TranslatableMarkup('Complete'),
      '#access' => !$form['checkbox']['#disabled'],
      '#ajax' => [
        'callback' => [ChecklistRowForm::class, 'onCompleteAjaxCallback'],
        'wrapper' => $wrapper_id,
      ],
      '#attributes' => [
        'class' => [
          'js-hide',
        ]
      ]
    ];

    // If the item is complete and it is reversible, we want to mutate the
    // 'complete' button into a 'reverse' button. We do this by changing the
    // value and callbacks.
    if ($item->isComplete() && $is_reversible) {
      $form['complete']['#value'] = new TranslatableMarkup('Reverse');
      $form['complete']['#ajax']['callback'] = [ChecklistRowForm::class, 'onReverseAjaxCallback'];
      $form['complete']['#is_reversing'] = TRUE;
    }

    return $form;
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the plugin form as built
   *   by static::buildConfigurationForm().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\checklist\Entity\ChecklistItemInterface $item */
    $item = $this->plugin->getItem();
    if (empty($form_state->getTriggeringElement()['#is_reversing'])) {
      $item->setComplete(ChecklistItemInterface::METHOD_MANUAL);
    }
    else {
      $item->setIncomplete();
    }
    $item->save();

    $form_state->setRebuild();
  }
}
