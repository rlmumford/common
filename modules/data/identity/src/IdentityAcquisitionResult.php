<?php

namespace Drupal\identity;

use Drupal\identity\Entity\Identity;

class IdentityAcquisitionResult {

  /**
   * Method constants.
   */
  const METHOD_REFERENCE = 2;
  const METHOD_FOUND = 1;
  const METHOD_CREATE = 0;
  const METHOD_QUEUED = -1;

  /**
   * @var \Drupal\identity\Entity\Identity
   */
  protected $identity;

  /**
   * @var array
   */
  protected $matches;

  /**
   * @var int
   */
  protected $method;

  /**
   * IdentityAcquisitionResult constructor.
   *
   * @param \Drupal\identity\Entity\Identity $identity
   * @param int $method
   * @param \Drupal\identity\IdentityMatch[] $matches
   */
  public function __construct(Identity $identity = NULL, $method = self::METHOD_FOUND, array $matches = []) {
    if ($method != static::METHOD_QUEUED && !$identity) {
      throw new \Exception('No identity suppled to result');
    }

    $this->identity = $identity;
    $this->method = $method;
    $this->matches = $matches;
  }

  /**
   * Get the method.
   *
   * @return int
   */
  public function getMethod() {
    return $this->method;
  }

  /**
   * Get the acquired identity.
   *
   * @return \Drupal\identity\Entity\Identity
   */
  public function getIdentity() {
    return $this->identity;
  }

  /**
   * Get all the matches.
   *
   * @return array|\Drupal\identity\IdentityMatch[]
   */
  public function getAllMatches() {
    return $this->matches;
  }
}
