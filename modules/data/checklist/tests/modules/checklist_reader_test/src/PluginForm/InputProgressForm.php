<?php

namespace Drupal\checklist_reader_test\PluginForm;

use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Collects input for the same operation exposed to API/tool callers.
 */
class InputProgressForm extends PluginFormBase {
  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $attempt = $this->plugin->inputAttempt();
    $form['reference'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Document reference'),
      '#description' => $this->t('Enter the reference printed on the remaining document.'),
      '#required' => TRUE,
    ];
    // Value elements retain the original request version in cached form state.
    $form['attempt_id'] = ['#type' => 'value', '#value' => $attempt?->id];
    $form['version'] = ['#type' => 'value', '#value' => $attempt?->version];
    $form['actions']['complete']['#value'] = $this->t('Submit and continue');
    $form['actions']['complete']['#disabled'] = !$attempt;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->plugin->validateReference($form_state->getValue('reference'));
    }
    catch (\InvalidArgumentException $exception) {
      $form_state->setErrorByName('reference', $exception->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->plugin->supplyReference($form_state->getValue('reference'), $form_state->getValue('attempt_id'), $form_state->getValue('version'));
    }
    catch (ChecklistAttemptConflictException | \DomainException $exception) {
      // A submit-time race must not turn into a silent overwrite or completion.
      $this->messenger()->addError($this->t('This input request has changed. Reload the checklist before submitting again.'));
      $form_state->setRebuild();
    }
  }

}
