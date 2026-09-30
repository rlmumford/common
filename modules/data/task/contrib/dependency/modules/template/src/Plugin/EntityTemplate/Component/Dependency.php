<?php

namespace Drupal\task_dependency_template\Plugin\EntityTemplate\Component;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginTrait;
use Drupal\entity_template\Plugin\EntityTemplate\Component\ComponentBase;
use Drupal\entity_template\Plugin\EntityTemplate\Component\TemplateContextAwareComponentInterface;
use Drupal\entity_template\Plugin\EntityTemplate\Component\TemplateContextAwareComponentTrait;
use Drupal\entity_template\TemplateResult;
use Drupal\task\TaskInterface;
use Drupal\task_dependency\DependencyManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Binds a new task's dependency to a context of its creation template.
 *
 * @EntityTemplateComponent(
 *   id = "task_dependency",
 *   label = @Translation("Task dependency"),
 *   category = @Translation("Tasks"),
 *   applies_to = {"entity:task"}
 * )
 */
class Dependency extends ComponentBase implements TemplateContextAwareComponentInterface, ContextAwarePluginInterface, ContainerFactoryPluginInterface {
  use TemplateContextAwareComponentTrait;
  use ContextAwarePluginTrait;

  /**
   * Constructs the template component.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected DependencyManager $dependencies, protected ContextHandlerInterface $contextHandler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('task_dependency.manager'), $container->get('context.handler'));
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinitions() {
    return ['target' => EntityContextDefinition::create($this->configuration['entity_type'] ?? 'task')->setLabel($this->t('Dependency target'))];
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinition($name) {
    return $this->getContextDefinitions()[$name] ?? throw new \InvalidArgumentException('Unknown dependency context.');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'trigger' => 'task.resolved',
      'action' => 'activate',
      'entity_type' => 'task',
      'field' => 'status',
      'property' => 'value',
      'value' => '',
      'context_mapping' => [],
      'follow_replacement' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function apply(EntityInterface $entity, TemplateResult $result) {
    if (!$entity instanceof TaskInterface) {
      throw new \InvalidArgumentException('Dependencies can only be attached to tasks.');
    }
    $this->context = [];
    $this->contextHandler->applyContextMapping($this, $this->getContextProvidingTemplate()->getContexts());
    $configuration = $this->getConfiguration();
    $dependency = $this->dependencies->create($entity, $configuration['trigger'], array_intersect_key($configuration, array_flip([
      'field',
      'property',
      'value',
    ])), $configuration['action'], $this->getContextValue('target'), $configuration['follow_replacement']);
    $entity->get('event_dependencies')->appendItem(['entity' => $dependency]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();
    $form['trigger'] = [
      '#type' => 'select',
      '#title' => $this->t('Event'),
      '#options' => [
        'task.resolved' => $this->t('Task resolves'),
        'entity.state' => $this->t('Entity enters a state'),
      ],
      '#default_value' => $config['trigger'],
    ];
    $form['action'] = [
      '#type' => 'select',
      '#title' => $this->t('Action'),
      '#options' => [
        'activate' => $this->t('Activate'),
        'invalidate' => $this->t('Invalidate'),
      ],
      '#default_value' => $config['action'],
    ];
    foreach ([
      'entity_type' => 'Target entity type',
      'field' => 'State field',
      'property' => 'State property',
      'value' => 'Qualifying value',
    ] as $key => $label) {
      $form[$key] = ['#type' => 'textfield', '#title' => $label, '#default_value' => $config[$key]];
    }
    $form['context_mapping']['#tree'] = TRUE;
    $form['context_mapping']['target'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Target context selector'),
      '#description' => $this->t('Use a template context or Typed Data Plus selector, for example entity_current. The selected saved entity becomes the indexed binding.'),
      '#default_value' => $config['context_mapping']['target'] ?? '',
      '#required' => TRUE,
    ];
    $form['follow_replacement'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Follow explicit replacements'),
      '#default_value' => $config['follow_replacement'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    foreach (array_keys($this->defaultConfiguration()) as $key) {
      $this->configuration[$key] = $form_state->getValue($key);
    }
    $this->configuration['follow_replacement'] = (bool) $this->configuration['follow_replacement'];
  }

}
