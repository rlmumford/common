<?php

namespace Drupal\note\Entity;

interface NoteInterface {

  /**
   * Create a reply to a given note.
   *
   * @return \Drupal\note\Entity\NoteInterface
   */
  public function createReply();

}
