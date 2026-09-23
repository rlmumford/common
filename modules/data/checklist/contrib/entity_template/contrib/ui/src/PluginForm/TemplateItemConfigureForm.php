<?php

namespace Drupal\checklist_entity_template_ui\PluginForm;

use Drupal\checklist\Form\ConditionConfigurationForm;
use Drupal\checklist\Form\ConfigurationForm;
use Drupal\checklist_entity_template\PreparedEntityEditor;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity_template\TemplateSource;
use Drupal\entity_template_ui\Form\TemplateSourceConfigurationForm;
use Drupal\flexiform\FormFactory;
use Drupal\flexiform\FormPluginManager;
use Drupal\typed_data_plus\Plugin\Context\DataContextDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Composes template, condition and Flexiform plugin configuration forms.
 */
class TemplateItemConfigureForm extends PluginFormBase implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    protected TemplateSourceConfigurationForm $sources,
    protected TemplateSource $resolver,
    protected ConditionConfigurationForm $conditions,
    protected ContextHandlerInterface $contexts,
    protected FormFactory $forms,
    protected FormPluginManager $formPlugins,
    protected PluginFormFactoryInterface $pluginForms,
    protected PreparedEntityEditor $editor,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_template_ui.source_configuration_form'),
      $container->get('entity_template.source'),
      $container->get('checklist.condition_configuration_form'),
      $container->get('context.handler'),
      $container->get('flexiform.form_factory'),
      $container->get('plugin.manager.flexiform.form'),
      $container->get('plugin_form.factory'),
      $container->get('checklist_entity_template.editor'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;
    $configuration = $this->plugin->getConfiguration();
    $contexts = $form_state->getTemporaryValue('gathered_contexts') ?? [];
    if ($this->plugin->getPluginId() === 'entity_template__apply_to') {
      $form['context_mapping'] = ['#type' => 'fieldset', '#title' => $this->t('Existing entity')];
      $form['context_mapping']['target'] = $this->contexts->getContextSelectElement($contexts, new ContextDefinition('entity', $this->t('Entity to update')), $configuration['context_mapping']['target'] ?? '');
    }
    $parents = [...$form['#parents'], 'templates'];
    $form['templates'] = ['#type' => 'container'];
    foreach (ConfigurationForm::rows($configuration['templates'], $parents, $form_state) as $index => $row) {
      $settings = $row['configuration'];
      $element = [
        '#type' => 'details',
        '#title' => $row['name'] ?: $this->t('New template choice'),
        '#open' => TRUE,
        '#parents' => [...$parents, $index],
      ];
      $posted = ConfigurationForm::input($element, $form_state);
      $name = $posted['name'] ?? $row['name'];
      $element['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Choice machine name'),
        '#default_value' => $name,
      ];
      $element['remove'] = ['#type' => 'checkbox', '#title' => $this->t('Remove template choice')];
      $element['update'] = ConfigurationForm::button($element['#parents'], $this->t('Update template choice'));
      if ($name === '' || !empty($posted['remove'])) {
        $form['templates'][$index] = $element;
        continue;
      }
      $element['template'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Template'),
        '#parents' => [...$element['#parents'], 'template'],
      ];
      $substate = SubformState::createForSubform($element['template'], $form, $form_state);
      $element['template'] = $this->sources->build($element['template'], $substate, $settings['template'] ?? []);
      $template = $this->sources->template($element['template']);
      if ($template) {
        $template->setContextMapping($settings['context_mapping'] ?? []);
        $element['context_mapping'] = $this->contexts->getContextAssignmentElement($template, $contexts);
        unset($element['context_mapping']['_blueprint_result']);
        $element['context_mapping'] += [
          '#type' => 'details',
          '#title' => $this->t('Template input mappings'),
          '#open' => TRUE,
        ];
      }
      $element['condition'] = [
        '#type' => 'details',
        '#title' => $this->t('Choice availability'),
        '#parents' => [...$element['#parents'], 'condition'],
      ];
      $substate = SubformState::createForSubform($element['condition'], $form, $form_state);
      $element['condition'] = $this->conditions->build($element['condition'], $substate, $settings['condition'] ?? [], $contexts);
      $options = ['' => $this->t('- No editor -')];
      foreach ($this->formPlugins->getDefinitions() as $id => $definition) {
        if (isset($definition['forms']['configure'])) {
          $options[$id] = $definition['label'];
        }
      }
      $editor_id = $posted['editor_plugin'] ?? $settings['editor']['plugin'] ?? '';
      $element['editor_plugin'] = [
        '#type' => 'select',
        '#title' => $this->t('Prepared entity editor'),
        '#options' => $options,
        '#default_value' => $editor_id,
      ];
      $element['#editor_plugin'] = $editor_id;
      if ($editor_id !== '' && $template) {
        $editor_config = ($settings['editor']['plugin'] ?? '') === $editor_id ? $settings['editor']['configuration'] : [
          'data' => ['entity' => ['plugin' => 'provided_data']],
        ];
        $editor = $this->forms->create($editor_config, $editor_id);
        $editor->setExpectedContexts([
          'entity' => DataContextDefinition::fromDataDefinition($template->getTargetDefinition()),
        ]);
        $element['editor'] = [
          '#type' => 'details',
          '#title' => $this->t('Editor configuration'),
          '#open' => TRUE,
          '#parents' => [...$element['#parents'], 'editor'],
          '#editor' => $editor,
        ];
        $editor_form = $this->pluginForms->createInstance($editor, 'configure');
        $substate = SubformState::createForSubform($element['editor'], $form, $form_state);
        $element['editor'] = $editor_form->buildConfigurationForm($element['editor'], $substate);
      }
      $form['templates'][$index] = $element;
    }
    $form['templates']['add'] = ConfigurationForm::button($parents, $this->t('Add template choice'), TRUE);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $configuration = $this->plugin->getConfiguration();
    $old = array_values($configuration['templates']);
    $configuration['templates'] = [];
    foreach ($form_state->getValue('templates', []) as $index => $row) {
      if (!is_int($index) || !empty($row['remove']) || trim($row['name'] ?? '') === '') {
        continue;
      }
      $name = trim($row['name'] ?? '');
      $element = &$form['templates'][$index];
      if (!preg_match('/^[a-z][a-z0-9_]*$/D', $name) || isset($configuration['templates'][$name])) {
        $form_state->setError($element['name'], $this->t('Use a unique machine name starting with a lowercase letter, followed by lowercase letters, digits or underscores.'));
      }
      if (!isset($element['template'])) {
        $form_state->setError($element['name'], $this->t('Update the template choice to configure it before saving.'));
        continue;
      }
      $source = $this->sources->configuration($element['template'], SubformState::createForSubform($element['template'], $form, $form_state));
      $candidate = $old[$index] ?? [];
      $candidate['template'] = $source;
      $candidate['context_mapping'] = array_filter($row['context_mapping'] ?? [], static fn($selector) => $selector !== '');
      if ($source) {
        $template = $this->resolver->resolve($source);
        foreach ($template->getContextDefinitions() as $slot => $definition) {
          if ($slot !== '_blueprint_result' && $definition->isRequired() && empty($candidate['context_mapping'][$slot])) {
            $form_state->setError($element['name'], $this->t('Update the template choice and map its required @input input.', [
              '@input' => $slot,
            ]));
          }
        }
      }
      unset($candidate['condition'], $candidate['editor']);
      if ($condition = $this->conditions->configuration($element['condition'], SubformState::createForSubform($element['condition'], $form, $form_state))) {
        $candidate['condition'] = $condition;
      }
      if ($row['editor_plugin'] !== $element['#editor_plugin']) {
        $form_state->setError($element['editor_plugin'], $this->t('Update the template choice before configuring a different editor.'));
      }
      elseif ($row['editor_plugin'] !== '' && isset($element['editor'])) {
        $editor = $element['editor']['#editor'];
        $editor_form = $this->pluginForms->createInstance($editor, 'configure');
        $substate = SubformState::createForSubform($element['editor'], $form, $form_state);
        $editor_form->validateConfigurationForm($element['editor'], $substate);
        if (!$form_state->hasAnyErrors()) {
          $editor_form->submitConfigurationForm($element['editor'], $substate);
          $candidate['editor'] = ['plugin' => $editor->getPluginId(), 'configuration' => $editor->getConfiguration()];
          try {
            $this->editor->form($candidate['editor']);
          }
          catch (\InvalidArgumentException $exception) {
            $form_state->setError($element['editor_plugin'], $exception->getMessage());
          }
        }
      }
      $configuration['templates'][$name] = $candidate;
    }
    if (!$configuration['templates']) {
      $form_state->setError($form['templates'], $this->t('Configure at least one template choice.'));
    }
    if ($this->plugin->getPluginId() === 'entity_template__apply_to') {
      $configuration['context_mapping'] = $form_state->getValue('context_mapping', []);
    }
    if (!$form_state->hasAnyErrors()) {
      $candidate = clone $this->plugin;
      $candidate->setConfiguration($configuration);
      try {
        $candidate->expectedOutcomeDefinitions();
        $candidate->calculateDependencies();
        $form['#validated_configuration'] = $configuration;
      }
      catch (\InvalidArgumentException $exception) {
        $form_state->setError($form['templates'], $exception->getMessage());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->plugin->setConfiguration($form['#validated_configuration']);
  }

}
