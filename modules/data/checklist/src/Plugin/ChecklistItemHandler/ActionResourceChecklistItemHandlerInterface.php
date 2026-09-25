<?php

namespace Drupal\checklist\Plugin\ChecklistItemHandler;

use Drupal\checklist\ChecklistActionResource;

/**
 * Optional interface for handlers that contribute a contextual resource.
 *
 * Resource content is returned as a render array so normal Drupal access and
 * cache metadata continue to apply. Handlers may use the same resource key to
 * share a pane with another item.
 */
interface ActionResourceChecklistItemHandlerInterface extends ChecklistItemHandlerInterface {

  /**
   * Gets this item's resource, if it currently has one to display.
   *
   * This method must only describe the resource; it must not execute the item
   * or persist state.
   *
   * @return \Drupal\checklist\ChecklistActionResource|null
   *   The current resource or NULL when this item has none.
   */
  public function getActionResource(): ?ChecklistActionResource;

}
