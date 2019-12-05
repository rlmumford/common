<?php

namespace Drupal\identity_service\EventSubscriber;

use Drupal\identity\Event\IdentityEvents;
use Drupal\identity\Event\PostIdentityMergeEvent;
use GuzzleHttp\ClientInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class IdentityMergeSubscriber implements EventSubscriberInterface {

  /**
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * [@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      IdentityEvents::POST_MERGE => 'postMergeNotifySubscribers',
    ];
  }

  /**
   * IdentityMergeSubscriber constructor.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  /**
   * @param \Drupal\identity\Event\PostIdentityMergeEvent $event
   */
  public function postMergeNotifySubscribers(PostIdentityMergeEvent $event) {

  }
}
