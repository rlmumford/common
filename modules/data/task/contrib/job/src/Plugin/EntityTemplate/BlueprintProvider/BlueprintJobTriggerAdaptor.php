<?php

namespace Drupal\task_job\Plugin\EntityTemplate\BlueprintProvider;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_template\Blueprint;
use Drupal\task_job\JobInterface;
use Drupal\task_job\Plugin\JobTrigger\JobTriggerInterface;

class BlueprintJobTriggerAdaptor extends Blueprint {

  /**
   * @var \Drupal\task_job\JobInterface
   */
  protected $job;

  /**
   * @var \Drupal\task_job\Plugin\JobTrigger\JobTriggerInterface
   */
  protected $trigger;

  /**
   * BlueprintJobTriggerAdaptor constructor.
   *
   * @param \Drupal\task_job\JobInterface $job
   * @param \Drupal\task_job\Plugin\JobTrigger\JobTriggerInterface $trigger
   */
  public function __construct(JobInterface $job, JobTriggerInterface $trigger) {
    $this->job = $job;
    $this->trigger = $trigger;

    foreach ($this->getContextDefinitions() as $name => $definition) {
      if ($name === 'job') {
        $this->setContext($name, new Context($definition, $job));
      }
      else if ($name == 'trigger') {
        $this->setContext($name, new Context($definition, $trigger->getKey()));
      }
      else if ($trigger instanceof ContextAwarePluginInterface) {
        try {
          $this->setContext($name, $trigger->getContext($name));
        }
        catch (ContextException $e) {}
      }
    }
  }

  /**
   * Get the job
   *
   * @return \Drupal\task_job\JobInterface
   */
  public function getJob(): JobInterface {
    return $this->job;
  }

  /**
   * Get job trigger.
   *
   * @return \Drupal\task_job\Plugin\JobTrigger\JobTriggerInterface
   */
  public function getTrigger(): JobTriggerInterface {
    return $this->trigger;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExtraContextDefinitions() {
    // Add the job and the trigger as contexts, and any contexts from the
    // trigger

    return [
      'job' => new ContextDefinition(
          'entity:task_job',
          new TranslatableMarkup('Job'),
          FALSE,
          FALSE,
          new TranslatableMarkup('The job'),
          $this->getJob()
        ),
      'trigger' => new ContextDefinition(
          'string',
          new TranslatableMarkup('Trigger'),
          FALSE,
          FALSE,
          new TranslatableMarkup('The trigger being fired'),
          $this->getTrigger()->getKey()
        ),
      ] + $this->getTrigger()->getContextDefinitions()
        + parent::getExtraContextDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function getBuilder() {
    return $this->builderManager()->createInstance(
      'task_job:'.$this->getJob()->id()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTemplatesConfiguration() {
    if (!$this->templatesCollection) {
      $conf = $this->getTrigger()->getConfiguration();
      return [
        'default' => !empty($conf['template']) ? $conf['template'] : [
          'id' => 'default',
          'label' => new TranslatableMarkup('Template'),
          'uuid' => 'default',
          'components' => $this->getDefaultTemplateComponents(),
          'conditions' => [],
        ],
      ];
    }

    return $this->templatesCollection->getConfiguration();
  }

  /**
   * Get the default template components.
   *
   * @return array.
   */
  public function getDefaultTemplateComponents() : array {
    $components = [];

    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
    $field_manager = \Drupal::service('entity_field.manager');
    foreach ($field_manager->getFieldDefinitions('task', 'task') as $name => $definition) {
      if (
        !$definition->isRequired() || $definition->isReadOnly() ||
        $definition->isComputed() || ($name === 'job')
      ) {
        continue;
      }

      $components[$name] = [
        'id' => 'field.widget_input:task.'.$name,
        'uuid' => $name,
      ];
    }

    if ($this->getJob() && ($definitions = $this->getJob()->getContextDefinitions())) {
      foreach ($definitions as $key => $definition) {
        $components["context:{$key}"] = [
          'id' => 'task_context.data_select',
          'task_context' => $key,
          'uuid' => "context:{$key}",
        ];
      }
    }

    return $components;
  }

  /**
   * Get the template builder manager.
   *
   * @return \Drupal\entity_template\TemplateBuilderManager
   */
  protected function builderManager() {
    return \Drupal::service('plugin.manager.entity_template.builder');
  }

}
