<?php

namespace Drupal\identity_service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\identity\Entity\Identity;

class IdentitySubscriber implements IdentitySubscriberInterface {

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $subscriptionStorage;

  /**
   * IdentitySubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Session\AccountInterface $current_user
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user
  ) {
    $this->subscriptionStorage = $entity_type_manager
      ->getStorage('identity_subsription');
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function subscribe(
    Identity $identity,
    $events,
    $notification_url,
    AccountInterface $subscriber = NULL
  ) {
    if (is_string($events)) {
      $events = [$events];
    }

    if (!$subscriber) {
      $subscriber = $this->currentUser;
    }

    $results = [];
    foreach ($events as $event) {
      // Try to load an identical subscription.
      $query = $this->subscriptionStorage->getQuery();
      $query->condition('notification_url', $notification_url);
      $query->condition('event', $event);
      $query->condition('identity', $identity->id());
      $query->range(0, 1);

      $ids = $query->execute();
      if (count($ids)) {
        $results[$event] = 'already_subscribed';
      }
      else {
        $this->subscriptionStorage->create([
          'identity' => $identity,
          'notification_url' => $notification_url,
          'event' => $event,
          'owner' => $subscriber->id(),
        ])->save();

        $results[$event] = 'subscribed';
      }
    }

    return $results;
  }
}
