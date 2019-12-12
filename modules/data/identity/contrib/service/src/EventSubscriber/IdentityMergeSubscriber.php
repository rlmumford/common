<?php

namespace Drupal\identity_service\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\identity\Event\IdentityEvents;
use Drupal\identity\Event\PostIdentityMergeEvent;
use Drupal\identity_service\IdentitySubscriptionNotifierInterface;
use GuzzleHttp\ClientInterface;
use function GuzzleHttp\Promise\settle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class IdentityMergeSubscriber implements EventSubscriberInterface {
  /**
   * @var \Drupal\identity_service\IdentitySubscriptionNotifierInterface
   */
  protected $notifier;

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
  public function __construct(IdentitySubscriptionNotifierInterface $notifier) {
    $this->notifier = $notifier;
  }

  /**
   * @param \Drupal\identity\Event\PostIdentityMergeEvent $event
   */
  public function postMergeNotifySubscribers(PostIdentityMergeEvent $event) {
    /** @var \Drupal\identity\Entity\Identity[] $identities */
    $identities = array_filter([
      $event->getIdentityOne(),
      $event->getIdentityTwo(),
    ]);

    /** @var \GuzzleHttp\Psr7\Request[] $requests */
    $requests = [];
    foreach ($identities as $identity) {
      $requests = array_merge(
        $requests,
        $this->notifier->notifyAsync($identity,IdentityEvents::POST_MERGE, [
          'identity' => [
            'id' => $identity->id(),
            'label' => $identity->label(),
            'uuid' => $identity->uuid(),
          ],
          'result_identity' => [
            'id' => $event->getIdentityResult()->id(),
            'label' => $event->getIdentityResult()->label(),
            'uuid' => $event->getIdentityResult()->uuid(),
          ],
        ])
      );
    }

    settle($requests)->wait();
  }
}
