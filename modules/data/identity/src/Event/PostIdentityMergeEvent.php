<?php

namespace Drupal\identity\Event;

use Drupal\identity\Entity\Identity;
use Symfony\Component\EventDispatcher\Event;

class PostIdentityMergeEvent extends Event {

  /**
   * @var \Drupal\identity\Entity\Identity
   */
  protected $identityOne;

  /**
   * @var \Drupal\identity\Entity\Identity
   */
  protected $identityTwo;

  /**
   * @var \Drupal\identity\Entity\Identity
   */
  protected $identityResult;

  /**
   * PostIdentityMergeEvent constructor.
   *
   * @param \Drupal\identity\Entity\Identity $identity_one
   * @param \Drupal\identity\Entity\Identity $identity_two
   * @param \Drupal\identity\Entity\Identity $identity_result
   */
  public function __construct(Identity $identity_one, Identity $identity_two, Identity $identity_result) {
    $this->identityOne = $identity_one;
    $this->identityTwo = $identity_two;
    $this->identityResult = $identity_result;
  }

  /**
   * Get the first identity
   *
   * @return \Drupal\identity\Entity\Identity
   */
  public function getIdentityOne() {
    return $this->identityOne;
  }

  /**
   * Get the second identity
   *
   * @return \Drupal\identity\Entity\Identity
   */
  public function getIdentityTwo() {
    return $this->identityTwo;
  }

  /**
   * Get the result identity.
   *
   * @return \Drupal\identity\Entity\Identity
   */
  public function getIdentityResult() {
    return $this->identityResult;
  }

}
