<?php

namespace Drupal\checklist;

/**
 * Collects resources available in a checklist workspace.
 */
interface ChecklistActionResourceCollectorInterface {

  /**
   * Collects accessible, currently actionable resources.
   *
   * Completed and failed items may still contribute resources for review or
   * recovery. For items sharing a key, all owner names are retained and the
   * last eligible item in checklist order supplies the rendered content.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The checklist being displayed.
   *
   * @return array
   *   Resources keyed by their shared key. Each value contains a resource
   *   descriptor and the names of all items that own that resource.
   */
  public function collect(ChecklistInterface $checklist): array;

}
