<?php

namespace Drupal\checklist\Plugin\ChecklistItemHandler;

/**
 * Opts an item into typed working state, separate from its public outcomes.
 */
interface StatefulChecklistItemHandlerInterface extends ChecklistItemHandlerInterface {

  /**
   * Defines the item's intermediate working values.
   *
   * Definitions must be reconstructible from the saved handler configuration.
   * They are not duplicated in each stored value or published as outcome
   * contexts. State is shared by the item, not implicitly scoped to a viewer.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   State definitions keyed by name.
   */
  public function stateDefinitions(): array;

}
