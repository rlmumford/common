<?php

namespace Drupal\checklist_communication_ui\PluginForm;

use Drupal\checklist_communication_ui\Form\OperationConfiguration;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\PluginFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures an operation on an existing context-mapped communication.
 */
class CommunicationOperationConfigureForm extends PluginFormBase implements ContainerInjectionInterface {

  public function __construct(protected OperationConfiguration $operations, protected ContextHandlerInterface $contexts) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('checklist_communication_ui.operation_configuration'), $container->get('context.handler'));
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $settings = $this->plugin->getConfiguration();
    $form['#tree'] = TRUE;
    $form['operation'] = $this->operations->build(['id' => $settings['operation']] + $settings);
    unset($form['operation']['label']);
    $form['context_mapping'] = $this->contexts->getContextAssignmentElement($this->plugin, $form_state->getTemporaryValue('gathered_contexts') ?? []);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->operations->validate($form['operation'], $form_state->getValue('operation'), $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $settings = $form_state->getValue('operation');
    $this->plugin->setConfiguration([
      'operation' => $settings['id'],
      'variant' => $settings['variant'],
      'confirm' => (bool) $settings['confirm'],
      'context_mapping' => $form_state->getValue('context_mapping', []),
    ] + $this->plugin->getConfiguration());
  }

}
