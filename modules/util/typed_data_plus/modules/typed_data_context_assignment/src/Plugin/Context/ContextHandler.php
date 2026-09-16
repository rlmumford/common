<?php

namespace Drupal\typed_data_context_assignment\Plugin\Context;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\typed_data_plus\Plugin\Context\ContextHandler as SharedContextHandler;

/**
 * Adds the original autocomplete widget to the shared context handler.
 */
class ContextHandler extends SharedContextHandler {

  /**
   * {@inheritdoc}
   */
  public function getContextAssignmentElement(ContextAwarePluginInterface $plugin, array $contexts) {
    $assignments = $plugin->getContextMapping();

    $element = ['#tree' => TRUE];
    foreach ($plugin->getContextDefinitions() as $context_slot => $definition) {
      $valid_contexts = $this->getAvailableContexts($contexts);

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
        '#title' => $definition->getLabel() ?: new TranslatableMarkup('Select a @context value:', ['@context' => $context_slot]),
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
