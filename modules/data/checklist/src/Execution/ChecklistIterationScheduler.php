<?php

namespace Drupal\checklist\Execution;

use Drupal\checklist\Attempt\ChecklistAttemptDispatchStorageInterface;
use Drupal\Core\Queue\QueueFactory;
use Psr\Log\LoggerInterface;

/**
 * Delivers due automatic attempts to the Queue API without executing handlers.
 */
class ChecklistIterationScheduler {

  public const QUEUE = 'checklist_iteration';

  /**
   * Constructs the scheduler.
   *
   * @param \Drupal\checklist\Attempt\ChecklistAttemptDispatchStorageInterface $storage
   *   Selects and reserves committed attempts for delivery.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   Configurable Queue API transport.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logs delivery failures without provider payloads.
   */
  public function __construct(
    protected ChecklistAttemptDispatchStorageInterface $storage,
    protected QueueFactory $queueFactory,
    protected LoggerInterface $logger,
  ) {}

  /**
   * Enqueues a bounded batch of already-authorized, committed attempts.
   *
   * This is an internal scheduler, not an API for authorizing submissions.
   * Reservations suppress repeat delivery for five minutes, independently of
   * execution claims. A crash before enqueue or a lost message can therefore
   * be repaired by a later scan. Duplicate messages are fenced by the runner.
   * Waiting results clear the reservation for their new attempt version.
   *
   * @param int $limit
   *   Maximum messages per scan, between one and 100.
   *
   * @return int
   *   Number of messages accepted by the queue.
   */
  public function dispatch(int $limit = 50): int {
    $deliveries = $this->storage->reserveDue($limit);
    if (!$deliveries) {
      return 0;
    }
    $queue = $this->queueFactory->get(self::QUEUE);
    $count = 0;
    foreach ($deliveries as $delivery) {
      try {
        if ($queue->createItem($delivery) === FALSE) {
          throw new \RuntimeException('The queue rejected the message.');
        }
        $count++;
      }
      catch (\Throwable) {
        // Retain the reservation: the transport may have accepted the message
        // before reporting failure. The next scan after expiry can retry.
        $this->logger->error('Checklist iteration delivery failed for attempt {attempt}; scheduling will retry.', ['attempt' => $delivery['attempt']]);
      }
    }
    return $count;
  }

}
