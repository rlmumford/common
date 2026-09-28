<?php

namespace Drupal\checklist\Event;

use Drupal\checklist\ChecklistActionResource;
use Drupal\checklist\ChecklistInterface;

/**
 * Collects additional resources for a checklist workspace.
 */
class ChecklistCollectResourcesEvent extends ChecklistEvent {

  /**
   * Resources keyed by their shared key.
   *
   * @var array
   */
  protected array $resources;

  /**
   * Constructs the resource collection event.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The checklist.
   * @param array $resources
   *   Resources already collected from checklist items.
   */
  public function __construct(
    ChecklistInterface $checklist,
    array $resources = [],
  ) {
    parent::__construct($checklist);
    $this->resources = $resources;
  }

  /**
   * Adds a resource to the checklist workspace.
   *
   * @param \Drupal\checklist\ChecklistActionResource $resource
   *   The resource to add.
   * @param string|null $owner
   *   Optional checklist item name that owns this resource.
   *
   * @return $this
   */
  public function addResource(ChecklistActionResource $resource, ?string $owner = NULL): static {
    $key = $resource->getKey();
    if (!isset($this->resources[$key])) {
      $this->resources[$key] = [
        'resource' => $resource,
        'owners' => [],
      ];
    }
    else {
      $this->resources[$key]['resource'] = $resource;
    }

    if ($owner !== NULL) {
      $this->resources[$key]['owners'][] = $owner;
    }

    return $this;
  }

  /**
   * Gets all collected resources.
   *
   * @return array
   *   Resources keyed by shared key, with descriptor and owner entries.
   */
  public function getResources(): array {
    return $this->resources;
  }

}
