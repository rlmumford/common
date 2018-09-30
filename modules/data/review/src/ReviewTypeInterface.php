<?php

namespace Drupal\review;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Interface for review type entities.
 */
interface ReviewTypeInterface extends ConfigEntityInterface {

  /**
   * Get the target entity type id.
   *
   * @return string
   */
  public function getTargetEntityTypeId();

}
