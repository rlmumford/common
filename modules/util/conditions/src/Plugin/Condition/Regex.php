<?php

namespace Drupal\rlm_conditions\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\typed_data_context_assignment\Plugin\ContextAwarePluginAssignmentTrait;

/**
 * Condition that checks a string against a regular expression.
 *
 * @Condition(
 *   id = "regex",
 *   label = @Translation("Regular Expression"),
 *   context_definitions = {
 *     "string" = @ContextDefinition("string", label = @Translation("String"))
 *   }
 * );
 *
 * @package Drupal\rlm_conditions\Plugin\Condition
 */
class Regex extends ConditionPluginBase {
  use ContextAwarePluginAssignmentTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'pattern' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['pattern'] = [
      '#type' => 'textfield',
      '#title' => 'Pattern',
      '#description' => 'The pattern that should be matched by the context. Pattern must include delimiters, for example "/pattern/". To perform case-insensitive matching you can add the "i" flag after the closing delimiter, for example "/pattern/i" will match "PaTTern" as well as "pattern". For more information see this <a href="https://www.debuggex.com/cheatsheet/regex/pcre" target="_blank">cheatsheet</a>.',
      '#default_value' => $this->configuration['pattern'] ?? '',
      '#required' => TRUE,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['pattern'] = $form_state->getValue('pattern');

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    return preg_match($this->configuration['pattern'], $this->getContextValue('string'));
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    $replacements = [
      '@pattern' => $this->configuration['pattern'],
      '@context' => $this->configuration['context_mapping']['string'],
    ];

    if (!empty($this->configuration['negate'])) {
      return $this->t('The string (@context) is NOT matched by @pattern', $replacements);
    }
    else {
      return $this->t('The string (@context) is matched by @pattern', $replacements);
    }
  }

}
