<?php

namespace Drupal\checklist_communication\PluginForm;

use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Confirms the same advertised operation available to API/tool callers.
 */
class OperationConfirmationForm extends PluginFormBase {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $attempt = $this->plugin->inputAttempt();
    $form['message'] = ['#markup' => $this->t('Continue with the configured operation for this communication.')];
    $form['attempt_id'] = ['#type' => 'value', '#value' => $attempt?->id];
    $form['version'] = ['#type' => 'value', '#value' => $attempt?->version];
    $form['actions']['complete']['#value'] = $this->t('Confirm operation');
    $form['actions']['complete']['#disabled'] = !$attempt;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->plugin->confirm($form_state->getValue('attempt_id'), $form_state->getValue('version'));
      $this->messenger()->addStatus($this->t('Communication operation queued.'));
      $host = $this->plugin->getItem()->get('checklist')->checklist->getEntity();
      if ($host->hasLinkTemplate('canonical')) {
        $form_state->setRedirectUrl($host->toUrl());
      }
      else {
        $form_state->setRebuild();
      }
    }
    catch (ChecklistAttemptConflictException | \DomainException $exception) {
      $this->messenger()->addError($this->t('The operation changed. Reload before confirming again.'));
      $form_state->setRebuild();
    }
  }

}
