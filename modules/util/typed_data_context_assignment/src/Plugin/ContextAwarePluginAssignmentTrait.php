<?php

namespace Drupal\typed_data_context_assignment\Plugin;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait as CoreContextAwarePluginAssignmentTrait;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Site\Settings;

/**
 * Trait for plugins that want to use typed_data_context_assignment.
 */
trait ContextAwarePluginAssignmentTrait {
  use CoreContextAwarePluginAssignmentTrait;

  /**
   * {@inheritdoc}
   */
  protected function addContextAssignmentElement(ContextAwarePluginInterface $plugin, array $contexts) {
    $assignments = $plugin->getContextMapping();

    $element = ['#tree' => TRUE];
    foreach ($plugin->getContextDefinitions() as $context_slot => $definition) {
      $valid_contexts = $this->contextHandler()->getMatchingContexts($contexts, $definition);

      $key_value_storage = \Drupal::keyValue('typed_data_context_assignment_autocomplete');
      $data = serialize($definition);
      $required_context_key = Crypt::hmacBase64($data, Settings::getHashSalt());
      $key_value_storage->set($required_context_key, $definition);

      $available_definitions = [];
      foreach ($valid_contexts as $name => $context) {
        $available_definitions[$name] = $context->getContextDefinition();
      }
      $available_context_key = Crypt::hmacBase64(serialize($available_definitions), Settings::getHashSalt());
      $key_value_storage->set($available_context_key, $available_definitions);

      $element[$context_slot] = [
        '#title' => $definition->getLabel() ?: $this->t('Select a @context value:', ['@context' => $context_slot]),
        '#type' => 'textfield',
        '#description' => $definition->getDescription(),
        '#required' => $definition->isRequired(),
        '#default_value' => !empty($assignments[$context_slot]) ? $assignments[$context_slot] : '',
        '#autocomplete_route_name' => 'typed_data_context_assignment.data_select_autocomplete',
        '#autocomplete_route_parameters' => [
          'required_context_key' => $required_context_key,
          'available_context_key' => $available_context_key,
        ],
      ];
    }

    return $element;
  }

}
