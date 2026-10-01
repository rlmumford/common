<?php

namespace Drupal\checklist_flexiform_ui\PluginForm;

use Drupal\checklist\Form\ConfigurationForm;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\flexiform\FormFactory;
use Drupal\flexiform\FormPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Embeds the normal Flexiform configuration UI and checklist context mappings.
 */
class FlexiformConfigureForm extends PluginFormBase implements ContainerInjectionInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  public function __construct(
    protected FormFactory $forms,
    protected FormPluginManager $plugins,
    protected PluginFormFactoryInterface $pluginForms,
    protected ContextHandlerInterface $contexts,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('flexiform.form_factory'), $container->get('plugin.manager.flexiform.form'), $container->get('plugin_form.factory'), $container->get('context.handler'));
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;
    $configuration = $this->plugin->getConfiguration();
    $configuration_key = ['checklist_flexiform_configuration', hash('sha256', serialize($form['#parents']))];
    $configuration['form'] = $form_state->get($configuration_key) ?? $configuration['form'];
    $posted = ConfigurationForm::input($form, $form_state);
    $id = $posted['form_plugin'] ?? $configuration['form']['plugin'];
    $options = [];
    foreach ($this->plugins->getDefinitions() as $key => $definition) {
      if (isset($definition['forms']['configure'])) {
        $options[$key] = $definition['label'];
      }
    }
    $form['form_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Form type'),
      '#options' => $options,
      '#default_value' => $id,
    ];
    $form['update'] = ConfigurationForm::button($form['#parents'], $this->t('Update form configuration'));
    $form['update']['#validate'] = [[$this, 'validateDisplayUpdate']];
    $form['update']['#submit'] = [[$this, 'submitDisplayUpdate']];
    $form['update']['#limit_validation_errors'] = [[...$form['#parents'], 'display']];
    $form['update']['#configuration_parents'] = $form['#parents'];
    $form['update']['#configuration_key'] = $configuration_key;
    $display = $this->forms->create($id === $configuration['form']['plugin'] ? $configuration['form']['configuration'] : [], $id);
    $form['display'] = [
      '#type' => 'details',
      '#title' => $this->t('Form configuration'),
      '#open' => TRUE,
      '#parents' => [...$form['#parents'], 'display'],
      '#display' => $display,
    ];
    $plugin_form = $this->pluginForms->createInstance($display, 'configure');
    $substate = SubformState::createForSubform($form['display'], $form, $form_state);
    $form['display'] = $plugin_form->buildConfigurationForm($form['display'], $substate);
    $contexts = $form_state->getTemporaryValue('gathered_contexts') ?? [];
    if ($id === $configuration['form']['plugin'] && ($id !== 'referenced' || !empty($configuration['form']['configuration']['form_id']))) {
      $candidate = clone $this->plugin;
      $candidate->setConfiguration($configuration);
      $form['context_mapping'] = $this->contexts->getContextAssignmentElement($candidate, $contexts);
      $form['context_mapping'] += ['#type' => 'details', '#title' => $this->t('Form input mappings'), '#open' => TRUE];
      $outcomes = array_flip($configuration['outcomes']);
      $form['outcomes'] = ['#type' => 'details', '#title' => $this->t('Published outcomes'), '#open' => TRUE];
      foreach ($display->getExpectedContexts() as $name => $definition) {
        $form['outcomes'][$name] = [
          '#type' => 'textfield',
          '#title' => $definition->getLabel(),
          '#description' => $this->t('Outcome machine name. Leave blank to keep this data private to the form.'),
          '#default_value' => $outcomes[$name] ?? '',
        ];
      }
    }
    return $form;
  }

  /**
   * Validates the definition before rebuilding its mapping controls.
   */
  public function validateDisplayUpdate(array &$complete_form, FormStateInterface $form_state): void {
    $button = $form_state->getTriggeringElement();
    $form = &NestedArray::getValue($complete_form, $button['#configuration_parents']);
    $display = $form['display']['#display'];
    $plugin_form = $this->pluginForms->createInstance($display, 'configure');
    $substate = SubformState::createForSubform($form['display'], $complete_form, $form_state);
    $plugin_form->validateConfigurationForm($form['display'], $substate);
  }

  /**
   * Retains the updated definition in the authoring form.
   */
  public function submitDisplayUpdate(array &$complete_form, FormStateInterface $form_state): void {
    $button = $form_state->getTriggeringElement();
    $form = &NestedArray::getValue($complete_form, $button['#configuration_parents']);
    $display = $form['display']['#display'];
    $plugin_form = $this->pluginForms->createInstance($display, 'configure');
    $substate = SubformState::createForSubform($form['display'], $complete_form, $form_state);
    $plugin_form->submitConfigurationForm($form['display'], $substate);
    $form_state->set($button['#configuration_key'], [
      'plugin' => $display->getPluginId(),
      'configuration' => $display->getConfiguration(),
    ]);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $display = $form['display']['#display'];
    if ($form_state->getValue('form_plugin') !== $display->getPluginId()) {
      $form_state->setError($form['form_plugin'], $this->t('Update the form configuration before saving a different form type.'));
      return;
    }
    $plugin_form = $this->pluginForms->createInstance($display, 'configure');
    $substate = SubformState::createForSubform($form['display'], $form, $form_state);
    $plugin_form->validateConfigurationForm($form['display'], $substate);
    if ($form_state->hasAnyErrors()) {
      return;
    }
    $plugin_form->submitConfigurationForm($form['display'], $substate);
    $configuration = $this->plugin->getConfiguration();
    $configuration['form'] = ['plugin' => $display->getPluginId(), 'configuration' => $display->getConfiguration()];
    $configuration['context_mapping'] = array_filter($form_state->getValue('context_mapping', []), static fn($value) => $value !== '');
    $configuration['outcomes'] = [];
    foreach ($form_state->getValue('outcomes', []) as $binding => $name) {
      $name = trim($name);
      if ($name === '') {
        continue;
      }
      if (isset($configuration['outcomes'][$name])) {
        $form_state->setError($form['outcomes'][$binding], $this->t('Outcome names must be unique.'));
      }
      $configuration['outcomes'][$name] = $binding;
    }
    $candidate = clone $this->plugin;
    $candidate->setConfiguration($configuration);
    try {
      $candidate->expectedOutcomeDefinitions();
      foreach ($candidate->getContextDefinitions() as $name => $definition) {
        if ($definition->isRequired() && empty($configuration['context_mapping'][$name])) {
          $form_state->setError($form['display'], $this->t('Update the form configuration, then map its required @name input.', ['@name' => $definition->getLabel()]));
        }
      }
      $candidate->calculateDependencies();
      $form['#validated_configuration'] = $configuration;
    }
    catch (\InvalidArgumentException $exception) {
      $form_state->setError($form['display'], $exception->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->plugin->setConfiguration($form['#validated_configuration']);
  }

}
