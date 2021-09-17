<?php

namespace Drupal\checklist\PluginForm;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Row form for items that start.
 */
class StartableItemRowForm extends PluginFormBase {

  /**
   * The checklist itme handler plugin.
   *
   * @var \Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $item = $this->plugin->getItem();

    $form['checkbox'] = [
      '#type' => 'checkbox',
      '#title' => new TranslatableMarkup('Complete?'),
      '#title_display' => 'invisible',
      '#default_value' => $item->isComplete(),
      '#disabled' => $item->isComplete() || $item->isFailed() || !$item->isActionable(),
      '#ajax' => [
        'callback' => '::onStartAjaxCallback',
        'wrapper' => $form['#wrapper_id'],
        'trigger_as' => ['name' => 'start'],
      ],
    ];

    $form['start'] = [
      '#type' => 'submit',
      '#value' => new TranslatableMarkup('Start'),
      '#access' => !$form['checkbox']['#disabled'],
      '#name' => 'start',
      '#ajax' => [
        'callback' => '::onStartAjaxCallback',
        'wrapper' => $form['#wrapper_id'],
      ],
      '#attributes' => [
        'class' => [
          'js-hide',
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $input = &$form_state->getUserInput();
    unset($input['checkbox']);

    $form_state->setRebuild();
  }

}
