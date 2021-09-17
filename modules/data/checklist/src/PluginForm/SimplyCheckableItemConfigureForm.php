<?php

namespace Drupal\checklist\PluginForm;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Config for for simply checkable handlers.
 */
class SimplyCheckableItemConfigureForm extends PluginFormBase {

  /**
   * The checklist item handler.
   *
   * @var \Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['reversible'] = [
      '#type' => 'checkbox',
      '#title' => new TranslatableMarkup('This checklist item can be uncompleted once it has been checked'),
      '#default_value' => !empty($this->plugin->getConfiguration()['reversible']),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $config = $this->plugin->getConfiguration();
    $config['reversible'] = !empty($form_state->getValue('reversible'));
    $this->plugin->setConfiguration($config);
  }

}
