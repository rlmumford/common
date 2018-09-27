<?php

namespace Drupal\service;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface for CounselKit service entities.
 */
interface ServiceInterface extends ContentEntityInterface {

  /**
   * Get ther service type.
   *
   * @return \Drupal\service\ServiceTypeInterface
   */
  public function getType();

}
