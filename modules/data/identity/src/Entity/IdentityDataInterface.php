<?php

namespace Drupal\identity\Entity;

use Drupal\identity\IdentityMatch;

interface IdentityDataInterface {

  /**
   * Get the identity of this data.
   *
   * @return \Drupal\identity\Entity\Identity
   */
  public function getIdentity();

  /**
   * Get the acquisition priority of this data.
   *
   * @return integer
   */
  public function acquisitionPriority();

  /**
   * Find matches for this data.
   *
   * @return \Drupal\identity\IdentityMatch[]
   */
  public function findMatches();

  /**
   * @param \Drupal\identity\IdentityMatch $match
   */
  public function supportOrOppose(IdentityMatch $match);

}
