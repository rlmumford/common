<?php

namespace Drupal\checklist\PluginForm;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Presents a decision using the handler's shared choice validation.
 */
class DecisionItemActionForm extends PluginFormBase {

  /**
   * The decision handler.
   *
   * @var \Drupal\checklist\Plugin\ChecklistItemHandler\Decision
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $options = [];
    $reason_options = [];
    foreach ($this->plugin->availableOptions() as $name => $option) {
      $options[$name] = $option['label'];
      if (!empty($option['require_reason'])) {
        $reason_options[] = $option['label'];
      }
    }
    $configuration = $this->plugin->getConfiguration();
    $presentation = $configuration['presentation'];
    if (!in_array($presentation, ['buttons', 'radios', 'select'], TRUE)) {
      throw new \InvalidArgumentException('Unknown decision presentation.');
    }
    if ($presentation === 'buttons') {
      $form['choice'] = [
        '#type' => 'item',
        '#title' => $configuration['question'],
      ];
      // Reuse the wrapper's AJAX callback and submit handler for every choice.
      $submit = $form['actions']['complete'] ?? ['#type' => 'submit'];
      unset($form['actions']['complete']);
      foreach ($options as $name => $label) {
        $form['actions']['choose_' . $name] = array_replace($submit, [
          '#value' => $label,
          '#name' => 'decision_choose_' . $name,
          '#decision_choice' => $name,
        ]);
      }
      if (!$options) {
        $form['choice']['#markup'] = new TranslatableMarkup('No choices are currently available.');
      }
    }
    else {
      $form['choice'] = [
        '#type' => $presentation,
        '#title' => $configuration['question'],
        '#options' => $options,
        '#required' => TRUE,
      ];
      if ($presentation === 'select') {
        $form['choice']['#empty_option'] = new TranslatableMarkup('- Choose -');
      }
      $form['actions']['complete']['#value'] = new TranslatableMarkup('Choose');
      $form['actions']['complete']['#disabled'] = !$options;
    }
    $form['reason'] = [
      '#type' => 'textarea',
      '#title' => new TranslatableMarkup('Reason'),
      '#description' => $reason_options
        ? new TranslatableMarkup('A reason is required for: @options.', ['@options' => implode(', ', $reason_options)])
        : new TranslatableMarkup('Optional explanation.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->plugin->validateChoice($this->selectedChoice($form_state), $form_state->getValue('reason') ?? '');
    }
    catch (\InvalidArgumentException | \DomainException $exception) {
      $form_state->setErrorByName('choice', $exception->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->plugin->choose($this->selectedChoice($form_state), $form_state->getValue('reason') ?? '');
  }

  /**
   * Reads a button's machine name or the selected radio/select value.
   */
  protected function selectedChoice(FormStateInterface $form_state): string {
    if ($this->plugin->getConfiguration()['presentation'] === 'buttons') {
      return $form_state->getTriggeringElement()['#decision_choice'] ?? '';
    }
    return $form_state->getValue('choice') ?? '';
  }

}
