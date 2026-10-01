<?php

namespace Drupal\checklist\PluginForm;

use Drupal\checklist\Form\ConditionConfigurationForm;
use Drupal\checklist\ChecklistContextMapping;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\checklist\Form\ConfigurationForm;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures named choices without creating or acting on a checklist item.
 */
class DecisionItemConfigureForm extends PluginFormBase implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(protected ConditionConfigurationForm $conditions, protected ContextHandlerInterface $contextHandler) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('checklist.condition_configuration_form'), $container->get('context.handler'));
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $configuration = $this->plugin->getConfiguration();
    $form['#tree'] = TRUE;
    $form['question'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Question'),
      '#required' => TRUE,
      '#default_value' => $configuration['question'],
    ];
    $form['presentation'] = [
      '#type' => 'select',
      '#title' => $this->t('Presentation'),
      '#options' => [
        'buttons' => $this->t('Separate buttons'),
        'radios' => $this->t('Radio buttons'),
        'select' => $this->t('Select list'),
      ],
      '#default_value' => $configuration['presentation'],
    ];
    $parents = [...$form['#parents'], 'options'];
    $form['options'] = ['#type' => 'details', '#title' => $this->t('Choices'), '#open' => TRUE];
    foreach (ConfigurationForm::rows($configuration['options'], $parents, $form_state) as $index => $row) {
      $option = $row['configuration'];
      $form['options'][$index] = ['#type' => 'fieldset', '#title' => $row['name'] ?: $this->t('New choice')];
      $element = &$form['options'][$index];
      $element['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Machine name'),
        '#default_value' => $row['name'],
      ];
      $element['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#default_value' => $option['label'] ?? '',
      ];
      $element['require_reason'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Require a reason'),
        '#default_value' => $option['require_reason'] ?? FALSE,
      ];
      $templates = $form_state->getTemporaryValue('checklist_templates') ?? [];
      if ($templates || !empty($option['template'])) {
        $element['template'] = [
          '#type' => 'select',
          '#title' => $this->t('Checklist template'),
          '#description' => $this->t('Activate these items when this choice is selected. Each choice has its own work and history.'),
          '#options' => array_map(static fn(array $template) => $template['label'], $templates),
          '#empty_option' => $this->t('- No additional items -'),
          '#empty_value' => '',
          '#default_value' => $option['template'] ?? '',
        ];
        $definitions = $form_state->getTemporaryValue('branch_context_definitions') ?? [];
        $mapping = ChecklistContextMapping::fromDefinitions($definitions, $option['context_mapping'] ?? []);
        $contexts = $form_state->getTemporaryValue('gathered_contexts') ?? [];
        $element['context_mapping'] = ['#tree' => TRUE];
        if (method_exists($this->contextHandler, 'getContextAssignmentElement')) {
          $element['context_mapping'] = $this->contextHandler->getContextAssignmentElement($mapping, $contexts);
        }
        else {
          foreach ($definitions as $name => $definition) {
            $matches = $this->contextHandler->getMatchingContexts($contexts, $definition);
            $element['context_mapping'][$name] = [
              '#type' => 'select',
              '#title' => $definition->getLabel(),
              '#options' => array_map(static fn($context) => $context->getContextDefinition()->getLabel(), $matches),
              '#empty_option' => $this->t('- Inherit -'),
              '#empty_value' => '',
              '#default_value' => $option['context_mapping'][$name] ?? '',
            ];
          }
        }
        $element['context_mapping']['#type'] = 'details';
        $element['context_mapping']['#title'] = $this->t('Template context mapping');
        $element['context_mapping']['#description'] = $this->t('Leave empty to inherit the job contexts.');
      }
      $element['remove'] = ['#type' => 'checkbox', '#title' => $this->t('Remove choice')];
      $element['available'] = [
        '#type' => 'details',
        '#title' => $this->t('Availability'),
        '#parents' => [...$parents, $index, 'available'],
      ];
      $state = SubformState::createForSubform($element['available'], $form, $form_state);
      $element['available'] = $this->conditions->build($element['available'], $state, $option['available'] ?? [], $form_state->getTemporaryValue('gathered_contexts') ?? []);
    }
    unset($element);
    $form['options']['add'] = ConfigurationForm::button($parents, $this->t('Add choice'), TRUE);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $options = [];
    $saved = array_values($this->plugin->getConfiguration()['options']);
    foreach ($form_state->getValue('options', []) as $index => $row) {
      if (!is_int($index) || !empty($row['remove'])) {
        continue;
      }
      $name = trim($row['name'] ?? '');
      $label = trim($row['label'] ?? '');
      if ($name === '' && $label === '') {
        continue;
      }
      if (!preg_match('/^[a-z][a-z0-9_]*$/D', $name) || isset($options[$name])) {
        $form_state->setError($form['options'][$index]['name'], $this->t('Use a unique machine name starting with a lowercase letter, followed by lowercase letters, digits or underscores.'));
      }
      if ($label === '') {
        $form_state->setError($form['options'][$index]['label'], $this->t('Enter a label for this choice.'));
      }
      $options[$name] = ['label' => $label, 'require_reason' => (bool) $row['require_reason']] + ($saved[$index] ?? []);
      unset($options[$name]['template'], $options[$name]['context_mapping']);
      if (!empty($row['template'])) {
        $options[$name]['template'] = $row['template'];
        $options[$name]['context_mapping'] = array_filter($row['context_mapping'] ?? []);
      }
      $element = &$form['options'][$index]['available'];
      $condition = $this->conditions->configuration($element, SubformState::createForSubform($element, $form, $form_state));
      unset($options[$name]['available']);
      if ($condition) {
        $options[$name]['available'] = $condition;
      }
    }
    if (!$options) {
      $form_state->setError($form['options'], $this->t('Add at least one named choice.'));
    }
    if (!$form_state->hasAnyErrors()) {
      $configuration = $this->plugin->getConfiguration();
      $configuration['question'] = trim($form_state->getValue('question'));
      $configuration['presentation'] = $form_state->getValue('presentation');
      $configuration['options'] = $options;
      $form['#validated_configuration'] = $configuration;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->plugin->setConfiguration($form['#validated_configuration']);
  }

}
