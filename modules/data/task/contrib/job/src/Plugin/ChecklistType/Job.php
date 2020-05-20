<?php

namespace Drupal\task_job\Plugin\ChecklistType;

use Drupal\checklist\ChecklistInterface;
use Drupal\checklist\Plugin\ChecklistType\ChecklistTypeBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\task_job\JobInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Job
 *
 * @ChecklistType(
 *   id = "job",
 *   label = @Translation("Job Checklist"),
 *   entity_type = "task",
 * )
 *
 * @package Drupal\task_job\Plugin\ChecklistType
 */
class Job extends ChecklistTypeBase {

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $jobStorage;

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return \Drupal\checklist\Plugin\ChecklistType\ChecklistTypeBase|\Drupal\task_job\Plugin\ChecklistType\Job
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('checklist_item'),
      $container->get('entity_type.manager')->getStorage('task_job')
    );
  }

  /**
   * Job constructor.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\Entity\EntityStorageInterface $item_storage
   * @param \Drupal\Core\Entity\EntityStorageInterface $job_storage
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    EntityStorageInterface $item_storage,
    EntityStorageInterface $job_storage
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $item_storage);

    $this->jobStorage = $job_storage;
  }

  protected function getJob() : JobInterface {
    $this->jobStorage->load($this->getConfiguration()['job']);
  }

  /**
   * Get the default items.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface
   */
  public function getDefaultItems() {
    // TODO: Implement getDefaultItems() method.
  }

}
