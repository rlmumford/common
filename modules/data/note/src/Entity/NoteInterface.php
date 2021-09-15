<?php

namespace Drupal\note\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Interface for note entities.
 */
interface NoteInterface extends EntityOwnerInterface, ContentEntityInterface {

  /**
   * Create a reply to a given note.
   *
   * @return \Drupal\note\Entity\NoteInterface
   *   The reply note entity.
   */
  public function createReply();

}
