<?php

namespace Drupal\checklist\Attempt;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Reserves attempt deliveries using the journal's database connection.
 */
class DatabaseChecklistAttemptDispatchStorage implements ChecklistAttemptDispatchStorageInterface {

  /**
   * Constructs the SQL dispatch storage.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The same connection used by the journal, claims and result writes.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Current worker time.
   */
  public function __construct(
    protected Connection $database,
    protected TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function reserveDue(int $limit = 50): array {
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
    $deliveries = [];
    foreach ($rows as $row) {
      $reserve = $this->database->update('checklist_attempt')
        ->fields(['dispatch_expires' => $now + 300])
        ->condition('id', $row->id)->condition('version', $row->version);
      $this->eligible($reserve, $now);
      if (!$reserve->execute()) {
        continue;
      }
      $deliveries[] = ['attempt' => $row->id, 'version' => (int) $row->version];
    }
    return $deliveries;
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
