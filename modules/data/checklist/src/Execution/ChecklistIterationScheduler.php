<?php

namespace Drupal\checklist\Execution;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
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
   * @param \Drupal\Core\Database\Connection $database
   *   The attempt database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Current worker time.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   Configurable Queue API transport.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logs delivery failures without provider payloads.
   */
  public function __construct(
    protected Connection $database,
    protected TimeInterface $time,
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
    if ($limit < 1 || $limit > 100) {
      throw new \InvalidArgumentException('The dispatch limit must be between 1 and 100.');
    }
    if ($this->database->inTransaction()) {
      throw new \LogicException('Dispatch must run after the submitting transaction commits.');
    }
    $now = $this->time->getCurrentTime();
    $query = $this->database->select('checklist_attempt', 'a')->fields('a', ['id', 'version']);
    $this->eligible($query, $now);
    $rows = $query->orderBy('dispatch_expires')->orderBy('available')->orderBy('created')->orderBy('id')
      ->range(0, $limit)->execute()->fetchAll();
    if (!$rows) {
      return 0;
    }
    $queue = $this->queueFactory->get(self::QUEUE);
    $count = 0;
    foreach ($rows as $row) {
      $reserve = $this->database->update('checklist_attempt')
        ->fields(['dispatch_expires' => $now + 300])
        ->condition('id', $row->id)->condition('version', $row->version);
      $this->eligible($reserve, $now);
      if (!$reserve->execute()) {
        continue;
      }
      try {
        if ($queue->createItem(['attempt' => $row->id, 'version' => (int) $row->version]) === FALSE) {
          throw new \RuntimeException('The queue rejected the message.');
        }
        $count++;
      }
      catch (\Throwable) {
        // Retain the reservation: the transport may have accepted the message
        // before reporting failure. The next scan after expiry can retry.
        $this->logger->error('Checklist iteration delivery failed for attempt {attempt}; scheduling will retry.', ['attempt' => $row->id]);
      }
    }
    return $count;
  }

  /**
   * Applies identical due/reservation constraints to reads and atomic writes.
   */
  protected function eligible($query, int $now): void {
    $query->condition('path', ChecklistAttempt::ACTION)
      ->condition('mode', ChecklistAttempt::INITIAL)
      ->condition('status', [ChecklistAttempt::QUEUED, ChecklistAttempt::WAITING], 'IN')
      ->isNull('claim_token')->condition('available', $now, '<=')
      ->condition('dispatch_expires', $now, '<=');
  }

}
