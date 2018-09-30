<?php

namespace Drupal\review;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface for CounselKit review entities.
 */
interface ReviewInterface extends ContentEntityInterface {

  /**
   * Get ther review type.
   *
   * @return \Drupal\review\ReviewTypeInterface
   */
  public function getType();

}
