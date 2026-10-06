<?php

namespace Drupal\task_job_additions;

use Drupal\task_job\Event\JobChecklistDefinitionsEvent;
use Drupal\task_job\JobChecklistExpansion;
use Drupal\task_job\JobInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resolves each recorded instance against the task's live named job version.
 */
class AdditionDefinitions implements EventSubscriberInterface {

  public function __construct(protected AdditionStorage $storage) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [JobChecklistDefinitionsEvent::NAME => 'collect'];
  }

  /**
   * Adds only persisted instances belonging to this task and job version.
   */
  public function collect(JobChecklistDefinitionsEvent $event): void {
    if ($event->task->isNew()) {
      return;
    }
    foreach ($this->storage->forTask($event->task->uuid()) as $receipt) {
      if ($receipt['job'] !== $event->job->getBaseJobId() || $receipt['job_version'] !== (string) $event->job->getVersion()) {
        continue;
      }
      $definitions = JobChecklistExpansion::instance($receipt['template'], $event->job->get('checklist_templates') ?: [], self::prefix($receipt['id']), self::contextMapping($event->job, $receipt['template']));
      if (array_intersect_key($event->definitions, $definitions)) {
        throw new \InvalidArgumentException('An addition collides with an existing checklist item name.');
      }
      foreach ($definitions as &$definition) {
        $definition['derivation']['addition'] = $receipt['id'];
      }
      unset($definition);
      $event->definitions += $definitions;
    }
  }

  /**
   * Accepts only declared job inputs as addition mapping destinations.
   */
  public static function contextMapping(JobInterface $job, string $template): array {
    $mapping = $job->get('checklist_templates')[$template]['addition_context_mapping'] ?? [];
    $destinations = [];
    foreach ($job->getContextDefinitions() as $name => $definition) {
      $destinations['task_context:' . $name] = TRUE;
    }
    if (array_diff_key($mapping, $destinations)) {
      throw new \InvalidArgumentException('Addition context mappings can only override declared job inputs.');
    }
    return $mapping;
  }

  /**
   * Returns a stable namespace for one addition and its local item names.
   */
  public static function prefix(string $id): string {
    return 'added_' . str_replace('-', '', $id) . '__';
  }

}
