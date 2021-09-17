<?php

namespace Drupal\checklist\PluginForm;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Form\ChecklistRowForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Row form for simply checkable handlers.
 */
class SimplyCheckableItemRowForm extends PluginFormBase {

  /**
   * The checklist item handler plugin.
   *
   * @var \Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $item = $this->plugin->getItem();
    $is_reversible = !empty($this->plugin->getConfiguration()['reversible']);
    $wrapper_id = $form['#wrapper_id'];

    $form['checkbox'] = [
      '#type' => 'checkbox',
      '#title' => new TranslatableMarkup('Complete?'),
      '#title_display' => 'invisible',
      '#default_value' => $item->isComplete(),
      '#disabled' => (!$is_reversible && $item->isComplete()) || $item->isFailed() || !$item->isActionable(),
      '#ajax' => [
        'callback' => '::onCompleteAjaxCallback',
        'wrapper' => $wrapper_id,
        'trigger_as' => ['name' => 'complete'],
      ],
    ];

    $form['complete'] = [
      '#type' => 'submit',
      '#value' => new TranslatableMarkup('Complete'),
      '#name' => 'complete',
      '#access' => !$form['checkbox']['#disabled'],
      '#ajax' => [
        'callback' => '::onCompleteAjaxCallback',
        'wrapper' => $wrapper_id,
      ],
      '#attributes' => [
        'class' => [
          'js-hide',
        ],
      ],
    ];

    // If the item is complete and it is reversible, we want to mutate the
    // 'complete' button into a 'reverse' button. We do this by changing the
    // value and callbacks.
    if ($item->isComplete() && $is_reversible) {
      $form['complete']['#value'] = new TranslatableMarkup('Reverse');
      $form['complete']['#ajax']['callback'] = [
        ChecklistRowForm::class,
        'onReverseAjaxCallback',
      ];
      $form['complete']['#is_reversing'] = TRUE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
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
