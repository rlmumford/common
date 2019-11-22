<?php

namespace Drupal\identity\Event;

use Drupal\identity\IdentityDataGroup;
use Symfony\Component\EventDispatcher\Event;

class PreAcquisitionEvent extends Event {

  /**
   * The identity data group
   *
   * @var \Drupal\identity\IdentityDataGroup
   */
  protected $identityDataGroup;

  /**
   * PreAcquisitionEvent constructor.
   *
   * @param \Drupal\identity\IdentityDataGroup $identity_data_group
   */
  public function __construct(IdentityDataGroup $identity_data_group) {
    $this->identityDataGroup = $identity_data_group;
  }

  /**
   * Get the identity group.
   *
   * @return \Drupal\identity\IdentityDataGroup
   */
  public function getIdentityDataGroup() {
    return $this->identityDataGroup;
  }
}
