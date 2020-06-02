<?php
/**
 * Created by PhpStorm.
 * User: Rob
 * Date: 28/05/2020
 * Time: 13:01
 */

namespace Drupal\checklist\PluginForm;

use Drupal\checklist\Form\ChecklistRowForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

class StartableItemRowForm extends PluginFormBase {

  /**
   * @var \Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $item = $this->plugin->getItem();

    /** @var \Drupal\checklist\ChecklistInterface $checklist */
    $checklist = $item->checklist->checklist;
    $wrapper_id = $checklist->getEntity()->getEntityTypeId()
      .'--'.str_replace(':', '--', $checklist->getKey())
      .'--action-form-container';

    $form['checkbox'] = [
      '#type' => 'checkbox',
      '#title' => new TranslatableMarkup('Complete?'),
      '#title_display' => 'invisible',
      '#default_value' => $item->isComplete(),
      '#disabled' => $item->isComplete() || $item->isFailed() || !$item->isActionable(),
      '#ajax' => [
        'wrapper' => $wrapper_id,
        'method' => 'html',
        'trigger_as' => ['name' => 'start'],
        'url' => Url::fromRoute(
          'checklist.item.action_form',
          [
            'entity_type' => $checklist->getEntity()->getEntityTypeId(),
            'entity_id' => $checklist->getEntity()->id(),
            'checklist' => $checklist->getKey(),
            'item_name' => $item->getName(),
          ]
        )
      ]
    ];

    $form['start'] = [
      '#type' => 'submit',
      '#value' => new TranslatableMarkup('Start'),
      '#access' => !$form['checkbox']['#disabled'],
      '#name' => 'start',
      '#ajax' => [
        'wrapper' => $wrapper_id,
        'method' => 'html',
        'url' => Url::fromRoute(
          'checklist.item.action_form',
          [
            'entity_type' => $checklist->getEntity()->getEntityTypeId(),
            'entity_id' => $checklist->getEntity()->id(),
            'checklist' => $checklist->getKey(),
            'item_name' => $item->getName(),
          ]
        )
      ],
      '#attributes' => [
        'class' => [
          'js-hide',
        ]
      ]
    ];

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
    $input = &$form_state->getUserInput();
    unset($input['checkbox']);

    $form_state->setRebuild();
  }
}
