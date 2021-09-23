<?php

namespace Drupal\task_job\Entity;

use Drupal\Component\Plugin\LazyPluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\task_job\JobInterface;
use Drupal\task_job\Plugin\JobTrigger\JobTriggerInterface;
use Drupal\task_job\Plugin\JobTrigger\LazyJobTriggerCollection;
use Drupal\typed_data\Context\ContextDefinition;

/**
 * Entity class for the Job entity.
 *
 * @ConfigEntityType(
 *   id = "task_job",
 *   label = @Translation("Task Job"),
 *   label_collection = @Translation("Task Jobs"),
 *   admin_permission = "administer task jobs",
 *   config_prefix = "task_job",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "context",
 *     "description",
 *     "default_checklist",
 *     "triggers",
 *   },
 *   handlers = {
 *     "list_builder" = "Drupal\task_job\Controller\JobListBuilder",
 *     "access" = "Drupal\entity\EntityAccessControlHandler",
 *     "permission_provider" = "Drupal\entity\EntityPermissionProvider",
 *     "form" = {
 *        "add" = "\Drupal\task_job\Form\JobForm",
 *        "default" = "\Drupal\task_job\Form\JobEditForm",
 *        "delete" = "\Drupal\Core\Entity\EntityDeleteForm",
 *      },
 *     "route_provider" = {
 *       "html" = "Drupal\entity\Routing\DefaultHtmlRouteProvider",
 *     }
 *   },
 *   links = {
 *     "collection" = "/admin/config/task/job",
 *     "add-form" = "/admin/config/task/job/add",
 *     "canonical" = "/admin/config/task/job/{task_job}",
 *     "edit-form" = "/admin/config/task/job/{task_job}/edit",
 *     "delete-form" = "/admin/config/task/job/{task_job}/delete",
 *   }
 * );
 *
 * @package Drupal\task_job\Entity
 */
class Job extends ConfigEntityBase implements JobInterface {

  /**
   * The triggers configuration.
   *
   * @var array
   */
  protected $triggers = [];

  /**
   * The trigger collection.
   *
   * @var \Drupal\Component\Plugin\LazyPluginCollection
   */
  protected $triggerCollection;

  /**
   * The default checklist configuration.
   *
   * @var array
   *
   * @codingStandardsIgnoreStart
   */
  protected $default_checklist = [];
  // @codingStandardsIgnoreEnd

  /**
   * The context required by this job.
   *
   * @var array
   *   Array of context configuration, each item has the following keys:
   *   - key: The name of the context.
   *   - label: The human readable label of the context.
   *   - type: The type of the context.
   *   - description: The description of this context.
   *   - multiple: True if the context accepts multiple of the value.
   *   - required: True if the context is required, FALSE otherwise.
   */
  protected $context = [];

  /**
   * Get the default checklist items for this job.
   *
   * @return array
   *   An array of checklist item configuration keyed by the name.
   *   Each item should have atleast the following keys:
   *     - name - The name of the checklist itm
   *     - label - The label of the checklist item
   *     - handler - The handler plugin used for the checklist item.
   *     - handler_configuration - The configuration to be passed to the plugin.
   */
  public function getChecklistItems(): array {
    return $this->get('default_checklist') ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function getTriggersConfiguration(): array {
    return $this->get('triggers') ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultTriggersConfiguration(): array {
    return [
      'manual' => [
        'id' => 'manual',
        'key' => 'manual',
        'template' => [],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getTriggerCollection(): LazyPluginCollection {
    if (!$this->triggerCollection) {
      $this->triggerCollection = new LazyJobTriggerCollection(
        $this,
        \Drupal::service('plugin.manager.task_job.trigger'),
        $this->getTriggersConfiguration() ?: $this->defaultTriggersConfiguration()
      );
    }

    return $this->triggerCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getTrigger(string $key): ?JobTriggerInterface {
    return $this->getTriggerCollection()->get($key);
  }

  /**
   * {@inheritdoc}
   */
  public function hasTrigger(string $key): bool {
    $triggers = $this->getTriggersConfiguration();
    return isset($triggers[$key]);
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinitions() {
    $definitions = [];

    foreach ($this->context as $key => $context) {
      $definitions[$key] = ContextDefinition::createFromArray($context);
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinition(string $key) {
    return isset($this->context[$key]) ? ContextDefinition::createFromArray($this->context[$key]) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function addContextDefinition(string $key, ContextDefinition $context_definition) {
    $this->context[$key] = $context_definition->toArray();
  }

  /**
   * {@inheritdoc}
   */
  public function removeContextDefinition(string $key) {
    unset($this->context[$key]);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    /** @var \Drupal\task_job\Plugin\JobTrigger\JobTriggerManagerInterface $trigger_manager */
    $trigger_manager = \Drupal::service('plugin.manager.task_job.trigger');
    $trigger_manager->updateTriggerIndex($this);
  }

}
