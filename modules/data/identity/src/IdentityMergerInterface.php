<?php

namespace Drupal\identity;

use Drupal\identity\Entity\Identity;

interface IdentityMergerInterface {

  /**
   * Merge two identities.`
   *
   * @param \Drupal\identity\Entity\Identity $identity_one
   * @param \Drupal\identity\Entity\Identity $identity_two
   *
   * @return \Drupal\identity\Entity\Identity
   */
  public function mergeIdentities(Identity $identity_one, Identity $identity_two);

}
