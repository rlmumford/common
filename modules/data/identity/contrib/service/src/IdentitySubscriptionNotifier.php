<?php

namespace Drupal\identity_service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\identity\Entity\Identity;
use GuzzleHttp\ClientInterface;
use function GuzzleHttp\Promise\settle;

class IdentitySubscriptionNotifier implements IdentitySubscriptionNotifierInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * IdentitySubscriptionNotifier constructor.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(ClientInterface $http_client, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->httpClient = $http_client;
  }

  /**
   * Asynchronousely notify.
   *
   * @param \Drupal\identity\Entity\Identity $identity
   * @param string $event
   * @param array $content
   *
   * @return \GuzzleHttp\Promise\PromiseInterface[]
   */
  public function notifyAsync(Identity $identity, $event, array $content) {
    $storage = $this->entityTypeManager->getStorage('identity_subscription');

    $ids = $storage->getQuery()
      ->condition('identity', $identity->id())
      ->condition('event', $event)
      ->execute();

    $requests = [];
    foreach ($storage->loadMultiple($ids) as $subscription) {
      $requests[] = $this->httpClient->postAsync(
        $subscription->notification_url->value,
        [
          'json' => [
            'event' => $event,
            'message' => $content,
          ],
        ]
      );
    }

    return $requests;
  }

  /**
   * Notify
   *
   * @param \Drupal\identity\Entity\Identity $identity
   * @param $event
   * @param array $content
   *
   * @return \Psr\Http\Message\ResponseInterface[]
   */
  public function notify(Identity $identity, $event, array $content) {
    $requests = $this->notifyAsync($identity, $event, $content);
    return settle($requests)->wait();
  }
}
