<?php

namespace Drupal\identity\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\identity\IdentityMatch;

interface IdentityDataInterface extends ContentEntityInterface {

  /**
   * Get the identity of this data.
   *
   * @return \Drupal\identity\Entity\Identity
   */
  public function getIdentity();

  /**
   * Get the identity id of this
   *
   * @return integer
   */
  public function getIdentityId();

  /**
   * Set the identity this data is associated with.
   *
   * @return static
   */
  public function setIdentity(IdentityInterface $identity);

  /**
   * @return \Drupal\identity\Entity\IdentityDataSource
   */
  public function getSource();

  /**
   * Set the identity data source.
   *
   * @param \Drupal\identity\Entity\IdentityDataSource $source
   *
   * @return \Drupal\identity\Entity\IdentityDataInterface
   */
  public function setSource(IdentityDataSource $source);

  /**
   * @param bool $skip
   *
   * @return static
   */
  public function skipIdentitySave($skip = TRUE);

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

  /**
   * Possible match support levels.
   *
   * @return string[]
   */
  public function possibleMatchSupportLevels();

  /**
   * Possible match opposition levels.
   *
   * @return string[]
   */
  public function possibleMatchOppositionLevels();

}
