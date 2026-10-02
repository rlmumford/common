<?php

namespace Drupal\checklist_communication_ui\Form;

use Drupal\communication\OperationPluginManager;
use Drupal\communication\OperationVariantPluginManager;
use Drupal\communication\Plugin\Communication\Operation\DeferredSaveOperationInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Shares operation controls between standalone and per-template authoring.
 */
class OperationConfiguration {

  use StringTranslationTrait;

  public function __construct(protected OperationPluginManager $operations, protected OperationVariantPluginManager $variants) {}

  /**
   * Builds controls without loading a communication or executing an operation.
   */
  public function build(array $settings): array {
    $options = [];
    foreach ($this->operations->getDefinitions() as $id => $definition) {
      if (is_subclass_of($definition['class'], DeferredSaveOperationInterface::class)) {
        $options[$id] = $definition['label'];
      }
    }
    $variants = ['' => $this->t('- Select the only applicable variant at runtime -')];
    foreach ($this->variants->getDefinitions() as $id => $definition) {
      if (is_subclass_of($definition['class'], DeferredSaveOperationInterface::class)) {
        $variants[$id] = $definition['label'] . ' (' . implode(', ', $definition['operations']) . ')';
      }
    }
    return [
      '#type' => 'details',
      '#title' => $this->t('Operation after saving'),
      '#open' => TRUE,
      'id' => [
        '#type' => 'select',
        '#title' => $this->t('Operation'),
        '#options' => $options,
        '#default_value' => $settings['id'] ?? 'send',
        '#required' => TRUE,
      ],
      'variant' => [
        '#type' => 'select',
        '#title' => $this->t('Operation variant'),
        '#options' => $variants,
        '#default_value' => $settings['variant'] ?? '',
      ],
      'confirm' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Ask for confirmation before running the operation'),
        '#default_value' => $settings['confirm'] ?? FALSE,
      ],
      'label' => [
        '#type' => 'textfield',
        '#title' => $this->t('Follow-up item title'),
        '#default_value' => $settings['label'] ?? 'Send communication',
        '#required' => TRUE,
      ],
    ];
  }

  /**
   * Validates plugin identities; runtime checks applicability.
   */
  public function validate(array $form, array $settings, FormStateInterface $form_state): void {
    $definition = $this->operations->getDefinition($settings['id'], FALSE);
    if (!$definition || !is_subclass_of($definition['class'], DeferredSaveOperationInterface::class)) {
      $form_state->setError($form['id'], $this->t('Select an operation supporting deferred persistence.'));
    }
    if ($settings['variant'] !== '') {
      $variant = $this->variants->getDefinitionsForOperation($settings['id'])[$settings['variant']] ?? NULL;
      if (!$variant || !is_subclass_of($variant['class'], DeferredSaveOperationInterface::class)) {
        $form_state->setError($form['variant'], $this->t('Select a compatible variant for this operation.'));
      }
    }
  }

}
