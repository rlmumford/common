<?php

namespace Drupal\task_job\Plugin\EntityTemplate\Builder;

use Drupal\Core\Url;
use Drupal\entity_template\BlueprintEntityStorageAdaptor;
use Drupal\entity_template\BlueprintEntityAdaptor;
use Drupal\entity_template\BlueprintStorageInterface;
use Drupal\entity_template\Plugin\EntityTemplate\BlueprintProvider\BlueprintProviderInterface;
use Drupal\entity_template\Plugin\EntityTemplate\Builder\BuilderBase;
use Drupal\task_job\JobInterface;

/**
 * Class JobTaskBuilder
 *
 * @EntityTemplateBuilder(
 *   id = "task_job",
 *   label = @Translation("Task Job"),
 *   deriver = "\Drupal\task_job\Plugin\Derivative\JobEntityTemplateBuilderDeriver"
 * )
 *
 * @package Drupal\task_job\Plugin\EntityTemplate\Builder
 */
class JobTaskBuilder extends BuilderBase {

  /**
   * @var \Drupal\task_job\JobInterface
   */
  protected $job;

  /**
   * Get the job.
   *
   * @return \Drupal\task_job\JobInterface
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getJob() : JobInterface {
    if (!$this->job) {
      $this->job = $this->entityTypeManager->getStorage('task_job')
        ->load($this->getPluginDefinition()['task_job']);
    }

    return $this->job;
  }

  /**
   * Get the default blueprint for this builder.
   *
   * @return \Drupal\entity_template\BlueprintInterface
   */
  public function getDefaultBlueprint() {
    return new BlueprintEntityAdaptor($this->getJob());
  }

  /**
   * Get the default blueprint storage.
   *
   * @param \Drupal\entity_template\Plugin\EntityTemplate\BlueprintProvider\BlueprintProviderInterface $provider
   *
   * @return \Drupal\entity_template\BlueprintStorageInterface
   */
  public function getDefaultBlueprintStorage(BlueprintProviderInterface $provider) {
    return new BlueprintEntityStorageAdaptor(
      $this->getJob(),
      $provider
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultBlueprintEditTemplateUrl(
    BlueprintStorageInterface $blueprint_storage,
    string $key
  ): Url {
    /** @var BlueprintEntityStorageAdaptor $blueprint_storage */
    return Url::fromRoute(
      'entity.task_job.edit_form',
      [
        'task_job' => $blueprint_storage->getEntity()->id(),
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getReturnType() {
    // These always create tasks.
    return 'entity:task';
  }
}
