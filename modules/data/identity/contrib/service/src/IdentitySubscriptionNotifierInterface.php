<?php

namespace Drupal\identity_service;

use Drupal\identity\Entity\Identity;

interface IdentitySubscriptionNotifierInterface {

  /**
   * Asynchronousely notify.
   *
   * @param \Drupal\identity\Entity\Identity $identity
   * @param string $event
   * @param array $content
   *
   * @return \GuzzleHttp\Promise\PromiseInterface[]
   */
  public function notifyAsync(Identity $identity, $event, array $content);

  /**
   * Notify
   *
   * @param \Drupal\identity\Entity\Identity $identity
   * @param $event
   * @param array $content
   *
   * @return \Psr\Http\Message\ResponseInterface[]
   */
  public function notify(Identity $identity, $event, array $content);

}
