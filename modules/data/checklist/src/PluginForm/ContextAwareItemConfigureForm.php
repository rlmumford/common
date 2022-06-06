<?php

namespace Drupal\checklist\PluginForm;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\typed_data_context_assignment\Plugin\ContextAwarePluginAssignmentTrait;

/**
 * Checklist item configure form for context aware checklist item handlers.
 */
class ContextAwareItemConfigureForm extends PluginFormBase {
  use ContextAwarePluginAssignmentTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    if ($this->plugin instanceof ContextAwarePluginInterface) {
      // Add context mapping UI form elements.
      $contexts = $form_state->getTemporaryValue('gathered_contexts') ?: [];
      $form['context_mapping'] = $this->addContextAssignmentElement($this->plugin, $contexts);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if ($this->plugin instanceof ContextAwarePluginInterface) {
      $this->plugin->setContextMapping($form_state->getValue('context_mapping'));
    }
  }
}
