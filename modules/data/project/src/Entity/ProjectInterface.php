<?php

namespace Drupal\project\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface for CounselKit project entities.
 */
interface ProjectInterface extends ContentEntityInterface {

  /**
   * Get the project type.
   *
   * @return \Drupal\project\Entity\ProjectTypeInterface
   */
  public function getType();

  /**
   * Get the manager entity.
   *
   * @return \Drupal\user\UserInterface
   *   The user managing the project.
   */
  public function getManager();

  /**
   * Get the manager id.
   *
   * @return integer|string
   *   The user id
   */
  public function getManagerId();

}
