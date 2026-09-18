<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\checklist\Attempt\ChecklistAttemptJournal;
use Drupal\checklist\Entity\ChecklistItem;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Core\Database\IntegrityConstraintViolationException;

/**
 * Tests durable attempt streams, conditional writes and atomic history.
 *
 * @group checklist
 */
class ChecklistAttemptJournalTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'text', 'filter', 'options', 'entity',
    'checklist', 'checklist_context_test', 'plugin_reference', 'typed_data',
    'typed_data_plus', 'typed_data_reference', 'typed_data_context_assignment',
    'inline_entity_form',
  ];

  /**
   * The attempt journal.
   *
   * @var \Drupal\checklist\Attempt\ChecklistAttemptJournal
   */
  protected ChecklistAttemptJournal $journal;

  /**
   * A clock independent of request start time.
   *
   * @var int
   */
  protected int $now = 1000;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('checklist', ['checklist_attempt', 'checklist_attempt_head', 'checklist_attempt_event']);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturnCallback(fn() => $this->now);
    $this->container->set('datetime.time', $time);
    $this->journal = $this->container->get('checklist.attempt_journal');
  }

  /**
   * Creates a UUID-bearing item without requiring a saved host or item.
   */
  protected function item(): ChecklistItem {
    return ChecklistItem::create([
      'checklist_type' => 'context_test',
      'name' => 'work',
      'handler' => ['id' => 'create_entity', 'configuration' => []],
    ]);
  }

  /**
   * History captures versions, identities and current time without item writes.
   */
  public function testLifecycle(): void {
    $item = $this->item();
    $this->assertNull($this->journal->latest($item));
    $attempt = $this->journal->create($item, 11, 22, ChecklistAttempt::ACTION_OPERATION, 'choose');
    $this->assertSame($item->uuid(), $attempt->itemUuid);
    $this->assertTrue($item->isNew());
    $this->assertTrue($item->isIncomplete());
    $this->assertNull($attempt->previous);
    $this->assertSame(ChecklistAttempt::INITIAL, $attempt->mode);
    $this->assertSame(ChecklistAttempt::QUEUED, $attempt->status);
    $this->assertSame(1, $attempt->version);
    $this->assertSame(11, $attempt->initiator);
    $this->assertSame(22, $attempt->executor);
    $this->assertSame(ChecklistAttempt::ACTION_OPERATION, $attempt->path);
    $this->assertSame('choose', $attempt->operation);
    $this->assertSame(1000, $attempt->created);
    $this->now = 1100;
    $running = $this->journal->transition($attempt, ChecklistAttempt::RUNNING, 22);
    $this->assertSame(1000, $running->created);
    $this->assertSame(1100, $running->changed);
    $waiting = $this->journal->transition($running, ChecklistAttempt::WAITING, 22, 'Awaiting provider');
    $this->now = 1200;
    $running = $this->journal->transition($waiting, ChecklistAttempt::RUNNING, 33);
    $done = $this->journal->transition($running, ChecklistAttempt::SUCCEEDED, 33);
    $this->assertTrue($done->isTerminal());
    $this->assertSame(5, $done->version);
    $this->assertSame(ChecklistAttempt::QUEUED, $attempt->status);
    $this->assertEquals($done, $this->journal->latest($item));
    $events = $this->journal->history($done->id);
    $this->assertCount(5, $events);
    $this->assertSame([1, 2, 3, 4, 5], array_column($events, 'version'));
    $this->assertSame([11, 22, 22, 33, 33], array_column($events, 'actor'));
    $this->assertNull($events[0]['from_status']);
    $this->assertSame('Awaiting provider', $events[2]['reason']);
    $this->assertSame(['version', 'from_status', 'to_status', 'actor', 'created', 'reason'], array_keys($events[0]));
    $this->assertSame(array_slice($events, 2, 2), $this->journal->history($done->id, 2, 2));
    $this->assertSame([], $this->journal->history($done->id, 5));
    $this->assertTrue($item->isIncomplete(), 'Journal transitions do not claim to coordinate item state.');
  }

  /**
   * Resume and fresh have separate linked records, preserving failed history.
   */
  public function testSuccessors(): void {
    $item = $this->item();
    $initial = $this->journal->create($item, 1, 2, ChecklistAttempt::ACTION);
    $running = $this->journal->transition($initial, ChecklistAttempt::RUNNING, 2);
    $failed = $this->journal->transition($running, ChecklistAttempt::FAILED, 2, 'Provider unavailable');
    $history = $this->journal->history($failed->id);
    $resume = $this->journal->create($item, 3, 4, ChecklistAttempt::ACTION, NULL, ChecklistAttempt::RESUME, $failed->id);
    $this->assertSame($failed->id, $resume->previous);
    $this->assertSame(ChecklistAttempt::RESUME, $resume->mode);
    $this->assertNotSame($failed->id, $resume->id);
    $this->assertSame(1, $resume->version);
    $superseded = $this->journal->transition($resume, ChecklistAttempt::SUPERSEDED, 3);
    $fresh = $this->journal->create($item, 5, 6, ChecklistAttempt::ACTION, NULL, ChecklistAttempt::FRESH, $superseded->id);
    $this->assertSame($superseded->id, $fresh->previous);
    $this->assertSame(ChecklistAttempt::FRESH, $fresh->mode);
    $this->assertEquals($fresh, $this->journal->latest($item));
    $this->assertEquals($failed, $this->journal->load($failed->id));
    $this->assertSame($history, $this->journal->history($failed->id));
    $this->assertNull($this->journal->latest($this->item()));
    $this->assertTrue($item->isNew());
  }

  /**
   * Duplicate/stale starts, wrong-item predecessors and late writes conflict.
   */
  public function testConflicts(): void {
    $item = $this->item();
    $attempt = $this->journal->create($item, 1, 2, ChecklistAttempt::ACTION);
    $this->assertConflict(fn() => $this->journal->create($item, 1, 2, ChecklistAttempt::ACTION));
    $running = $this->journal->transition($attempt, ChecklistAttempt::RUNNING, 2);
    $this->assertConflict(fn() => $this->journal->transition($attempt, ChecklistAttempt::RUNNING, 2));
    $failed = $this->journal->transition($running, ChecklistAttempt::FAILED, 2);
    $this->assertConflict(fn() => $this->journal->create($this->item(), 1, 2, ChecklistAttempt::ACTION, NULL, ChecklistAttempt::RESUME, $failed->id));
    $successor = $this->journal->create($item, 1, 2, ChecklistAttempt::ACTION, NULL, ChecklistAttempt::RESUME, $failed->id);
    $this->assertConflict(fn() => $this->journal->create($item, 1, 2, ChecklistAttempt::ACTION, NULL, ChecklistAttempt::FRESH, $failed->id));
    $this->assertConflict(fn() => $this->journal->transition($running, ChecklistAttempt::SUCCEEDED, 2));
    $this->assertEquals($successor, $this->journal->latest($item));
    $this->assertCount(3, $this->journal->history($failed->id));
    $this->assertCount(1, $this->journal->history($successor->id));
  }

  /**
   * Invalid lifecycle changes never append an event.
   */
  public function testInvalidTransitions(): void {
    $item = $this->item();
    $attempt = $this->journal->create($item, 1, 2, ChecklistAttempt::ACTION);
    foreach ([ChecklistAttempt::QUEUED, ChecklistAttempt::WAITING, ChecklistAttempt::SUCCEEDED, 'unknown'] as $status) {
      try {
        $this->journal->transition($attempt, $status, 2);
        $this->fail('Invalid transition was accepted.');
      }
      catch (\DomainException) {
        $this->assertCount(1, $this->journal->history($attempt->id));
      }
    }
    try {
      $this->journal->create($item, 1, 2, ChecklistAttempt::ACTION, NULL, ChecklistAttempt::FRESH, $attempt->id);
      $this->fail('An active attempt cannot be silently replaced.');
    }
    catch (\DomainException) {
      $this->assertEquals($attempt, $this->journal->latest($item));
    }
    $cancelled = $this->journal->transition($attempt, ChecklistAttempt::CANCELLED, 1);
    try {
      $this->journal->create($item, 1, 2, ChecklistAttempt::ACTION, NULL, ChecklistAttempt::RESUME, $cancelled->id);
      $this->fail('Resume requires a failed predecessor.');
    }
    catch (\DomainException) {
      $this->assertEquals($cancelled, $this->journal->latest($item));
    }
    $this->expectException(\DomainException::class);
    $this->journal->transition($cancelled, ChecklistAttempt::RUNNING, 2);
  }

  /**
   * History insertion failure rolls back the attempt update.
   */
  public function testTransitionRollback(): void {
    $attempt = $this->journal->create($this->item(), 1, 2, ChecklistAttempt::ACTION);
    $database = $this->container->get('database');
    // Force the history insertion to fail after the attempt row is updated.
    $database->insert('checklist_attempt_event')->fields([
      'attempt' => $attempt->id,
      'version' => 2,
      'from_status' => ChecklistAttempt::QUEUED,
      'to_status' => ChecklistAttempt::RUNNING,
      'actor' => 2,
      'created' => 1000,
    ])->execute();
    try {
      $this->journal->transition($attempt, ChecklistAttempt::RUNNING, 2);
      $this->fail('The duplicate event must fail.');
    }
    catch (IntegrityConstraintViolationException) {
      $this->assertEquals($attempt, $this->journal->load($attempt->id));
      $this->assertCount(2, $this->journal->history($attempt->id));
    }
  }

  /**
   * Interleaved creators cannot overwrite heads or duplicate first attempts.
   *
   * @dataProvider creationRaces
   */
  public function testCreationRace(bool $successor): void {
    $item = $this->item();
    $previous = NULL;
    $mode = ChecklistAttempt::INITIAL;
    if ($successor) {
      $previous = $this->journal->create($item, 1, 2, ChecklistAttempt::ACTION);
      $previous = $this->journal->transition($previous, ChecklistAttempt::CANCELLED, 1);
      $mode = ChecklistAttempt::FRESH;
    }
    // Simulate a competing commit between reading and replacing the head.
    $racing = new class($this->container->get('database'), $this->container->get('uuid'), $this->container->get('datetime.time')) extends ChecklistAttemptJournal {
      /**
       * Injected write after the head is read.
       *
       * @var \Closure
       */
      public \Closure $interleave;

      /**
       * {@inheritdoc}
       */
      public function latest(ChecklistItemInterface $item): ?ChecklistAttempt {
        $snapshot = parent::latest($item);
        ($this->interleave)();
        return $snapshot;
      }

    };
    $winner = NULL;
    $racing->interleave = function () use ($item, $mode, $previous, &$winner): void {
      $winner = $this->journal->create($item, 3, 4, ChecklistAttempt::ACTION, NULL, $mode, $previous?->id);
    };
    $this->assertConflict(fn() => $racing->create($item, 1, 2, ChecklistAttempt::ACTION, NULL, $mode, $previous?->id));
    // This fixture shares a transaction: the losing rollback also rolls back
    // the injected write. Its purpose is to exercise the SQL conflict path.
    $this->assertNull($this->journal->load($winner->id));
    $this->assertEquals($previous, $this->journal->latest($item));
  }

  /**
   * Provides both unique-head insertion and conditional replacement races.
   */
  public static function creationRaces(): array {
    return [[FALSE], [TRUE]];
  }

  /**
   * A competing transition between read and update fails its conditional write.
   */
  public function testTransitionRace(): void {
    $attempt = $this->journal->create($this->item(), 1, 2, ChecklistAttempt::ACTION);
    $racing = new class($this->container->get('database'), $this->container->get('uuid'), $this->container->get('datetime.time')) extends ChecklistAttemptJournal {
      /**
       * One-shot write after the attempt is read.
       *
       * @var \Closure|null
       */
      public ?\Closure $interleave = NULL;

      /**
       * {@inheritdoc}
       */
      public function load(string $id): ?ChecklistAttempt {
        $snapshot = parent::load($id);
        if ($this->interleave) {
          $callback = $this->interleave;
          $this->interleave = NULL;
          $callback();
        }
        return $snapshot;
      }

    };
    $racing->interleave = fn() => $this->journal->transition($attempt, ChecklistAttempt::RUNNING, 2);
    $this->assertConflict(fn() => $racing->transition($attempt, ChecklistAttempt::CANCELLED, 1));
    $this->assertEquals($attempt, $this->journal->load($attempt->id));
    $this->assertCount(1, $this->journal->history($attempt->id));
  }

  /**
   * A failed creation rolls back the attempt and its head pointer together.
   */
  public function testCreationRollback(): void {
    $item = $this->item();
    $broken = new class($this->container->get('database'), $this->container->get('uuid'), $this->container->get('datetime.time')) extends ChecklistAttemptJournal {

      /**
       * {@inheritdoc}
       */
      protected function appendEvent(string $id, int $version, ?string $from, string $to, int $actor, int $created, string $reason): void {
        throw new \RuntimeException('History unavailable');
      }

    };
    try {
      $broken->create($item, 1, 2, ChecklistAttempt::ACTION);
      $this->fail('A creation without history must fail.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('History unavailable', $exception->getMessage());
      $this->assertNull($this->journal->latest($item));
      $count = $this->container->get('database')->select('checklist_attempt', 'a')->countQuery()->execute()->fetchField();
      $this->assertEquals(0, $count);
    }
    $this->assertSame(1, $this->journal->create($item, 1, 2, ChecklistAttempt::ACTION)->version);
  }

  /**
   * Fresh installs and repeated updates keep identical durable history.
   */
  public function testUpgrade(): void {
    $database = $this->container->get('database');
    foreach (['checklist_attempt_head', 'checklist_attempt_event', 'checklist_attempt'] as $table) {
      $database->schema()->dropTable($table);
    }
    $this->container->get('module_handler')->loadInclude('checklist', 'install');
    checklist_update_10002();
    $this->assertEquals(0, $database->select('checklist_attempt', 'a')->countQuery()->execute()->fetchField());
    $item = $this->item();
    $attempt = $this->journal->create($item, 1, 2, ChecklistAttempt::ACTION);
    checklist_update_10002();
    $this->assertEquals($attempt, $this->journal->latest($item));
    $this->assertCount(1, $this->journal->history($attempt->id));
  }

  /**
   * Invalid metadata is rejected before any journal writes.
   */
  public function testInvalidMetadata(): void {
    $item = $this->item();
    $writes = [
      fn() => $this->journal->create($item, -1, 2, ChecklistAttempt::ACTION),
      fn() => $this->journal->create($item, 1, -2, ChecklistAttempt::ACTION),
      fn() => $this->journal->create($item, 1, 2, 'unknown'),
      fn() => $this->journal->create($item, 1, 2, ChecklistAttempt::ACTION_OPERATION),
      fn() => $this->journal->create($item, 1, 2, ChecklistAttempt::ACTION_FORM, 'choose'),
      fn() => $this->journal->create($item, 1, 2, ChecklistAttempt::ACTION, mode: ChecklistAttempt::RESUME),
      fn() => $this->journal->create($item, 1, 2, ChecklistAttempt::ACTION, mode: 'unknown'),
    ];
    foreach ($writes as $write) {
      try {
        $write();
        $this->fail('Invalid metadata must be rejected.');
      }
      catch (\InvalidArgumentException) {
        $this->assertNull($this->journal->latest($item));
      }
    }
    $attempt = $this->journal->create($item, 1, 2, ChecklistAttempt::ACTION_FORM);
    $this->assertNull($attempt->operation);
    try {
      $this->journal->transition($attempt, ChecklistAttempt::RUNNING, 2, str_repeat('x', 513));
      $this->fail('Oversized explanations must not be silently truncated.');
    }
    catch (\InvalidArgumentException) {
      $this->assertEquals($attempt, $this->journal->load($attempt->id));
    }
    $this->expectException(\InvalidArgumentException::class);
    $this->journal->history($attempt->id, 0, 101);
  }

  /**
   * Asserts optimistic-concurrency rejection.
   */
  protected function assertConflict(callable $write): void {
    try {
      $write();
      $this->fail('A stale or conflicting write must be rejected.');
    }
    catch (ChecklistAttemptConflictException) {
      $this->addToAssertionCount(1);
    }
  }

}
