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
    $form['choice'] = [
      '#type' => 'radios',
      '#title' => $this->plugin->getConfiguration()['question'],
      '#options' => $options,
      '#required' => TRUE,
    ];
    $form['reason'] = [
      '#type' => 'textarea',
      '#title' => new TranslatableMarkup('Reason'),
      '#description' => $reason_options
        ? new TranslatableMarkup('A reason is required for: @options.', ['@options' => implode(', ', $reason_options)])
        : new TranslatableMarkup('Optional explanation.'),
    ];
    $form['actions']['complete']['#value'] = new TranslatableMarkup('Choose');
    $form['actions']['complete']['#disabled'] = !$options;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->plugin->validateChoice($form_state->getValue('choice') ?? '', $form_state->getValue('reason') ?? '');
    }
    catch (\InvalidArgumentException | \DomainException $exception) {
      $form_state->setErrorByName('choice', $exception->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->plugin->choose($form_state->getValue('choice') ?? '', $form_state->getValue('reason') ?? '');
  }

}
