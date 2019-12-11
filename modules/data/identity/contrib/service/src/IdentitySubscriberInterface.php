<?php

namespace Drupal\identity_service;

use Drupal\Core\Session\AccountInterface;
use Drupal\identity\Entity\Identity;

interface IdentitySubscriberInterface {

  /**
   * Subscribe to an identity.
   *
   * @param \Drupal\identity\Entity\Identity $identity
   * @param $events
   * @param $notification_url
   * @param \Drupal\Core\Session\AccountInterface|NULL $subscriber
   *
   * @return array
   *   An array of results, one for each event. Either 'already_subscribed' or
   *   'subscribed'
   */
  public function subscribe(Identity $identity, $events, $notification_url, AccountInterface $subscriber = NULL);
}
