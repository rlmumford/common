<?php

namespace Drupal\task_job\Entity;

use Drupal\Component\Plugin\LazyPluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\Plugin\DefaultLazyPluginCollection;
use Drupal\entity_template\BlueprintInterface;
use Drupal\entity_template\BlueprintEntityAdaptor;
use Drupal\entity_template\Entity\BlueprintEntityInterface;
use Drupal\task_job\JobInterface;
use Drupal\task_job\Plugin\JobTrigger\JobTriggerInterface;
use Drupal\task_job\Plugin\JobTrigger\LazyJobTriggerCollection;

/**
 * Class Job
 *
 * @ConfigEntityType(
 *   id = "task_job",
 *   label = @Translation("Task Job"),
 *   admin_permission = "administer task jobs",
 *   config_prefix = "task_job",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "default_checklist",
 *     "triggers",
 *   },
 *   handlers = {
 *     "list_builder" = "Drupal\task_job\Controller\JobListBuilder", *
 *     "form" = {
 *        "add" = "\Drupal\task_job\Form\JobForm",
 *        "default" = "\Drupal\task_job\Form\JobEditForm",
 *        "delete" = "\Drupal\Core\Entity\EntityDeleteForm",
 *      },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
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
 *
 * @todo: Make it possible to load overrides.
 */
class Job extends ConfigEntityBase implements JobInterface {

  /**
   * @var \Drupal\Component\Plugin\LazyPluginCollection
   */
  protected $triggerCollection;

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
      ]
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
}
