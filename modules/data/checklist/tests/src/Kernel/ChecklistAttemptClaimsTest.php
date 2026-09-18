<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Attempt\ChecklistAttemptClaim;
use Drupal\checklist\Attempt\ChecklistAttemptClaims;
use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\checklist\Attempt\ChecklistAttemptJournal;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Tests iteration claims, delayed continuation and atomic result application.
 *
 * @group checklist
 */
class ChecklistAttemptClaimsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'text', 'filter', 'options', 'entity',
    'checklist', 'checklist_context_test', 'checklist_state_test',
    'plugin_reference', 'typed_data', 'typed_data_plus', 'typed_data_reference',
    'typed_data_context_assignment', 'inline_entity_form',
  ];

  /**
   * The journal.
   *
   * @var \Drupal\checklist\Attempt\ChecklistAttemptJournal
   */
  protected ChecklistAttemptJournal $journal;

  /**
   * The iteration coordinator.
   *
   * @var \Drupal\checklist\Attempt\ChecklistAttemptClaims
   */
  protected ChecklistAttemptClaims $claims;

  /**
   * Worker clock.
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
    $this->installEntitySchema('user');
    $this->installEntitySchema('checklist_item');
    $this->installConfig(['system', 'user']);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturnCallback(fn() => $this->now);
    $time->method('getRequestTime')->willReturn(1000);
    $this->container->set('datetime.time', $time);
    $this->journal = $this->container->get('checklist.attempt_journal');
    $this->claims = $this->container->get('checklist.attempt_claims');
    FieldStorageConfig::create([
      'field_name' => 'work',
      'entity_type' => 'user',
      'type' => 'checklist',
    ])->save();
    FieldConfig::create(['field_name' => 'work', 'entity_type' => 'user', 'bundle' => 'user'])->save();
  }

  /**
   * Creates persistent stateful work for result application.
   */
  protected function item(): ChecklistItemInterface {
    $host = User::create([
      'name' => $this->randomMachineName(),
      'work' => [
        'id' => 'context_test',
        'configuration' => [
          'default_items' => [
            'worker' => ['title' => 'Worker', 'handler' => 'state_test', 'handler_configuration' => []],
          ],
        ],
      ],
    ]);
    $host->save();
    $item = $host->work->checklist->getItem('worker');
    $item->setWorkingState('run_id', 'provider-123')->save();
    return $item;
  }

  /**
   * Loads authoritative item data, discarding callback-mutated objects.
   */
  protected function reload(ChecklistItemInterface $item): ChecklistItemInterface {
    return $this->container->get('entity_type.manager')->getStorage('checklist_item')->loadUnchanged($item->id());
  }

  /**
   * Polling and batches continue one attempt with durable state and due times.
   */
  public function testIterations(): void {
    $item = $this->item();
    $attempt = $this->journal->create($item, 11, 22, ChecklistAttempt::ACTION);
    $this->assertSame([$attempt->id], $this->claims->due());
    $claim = $this->claims->claim($attempt, 60);
    $this->assertSame(ChecklistAttempt::RUNNING, $claim->attempt->status);
    $this->assertSame([], $this->claims->due());
    $this->assertFalse($this->container->get('database')->inTransaction());
    $waiting = $this->claims->commit($claim, ChecklistAttempt::WAITING, function () use ($item): void {
      $this->reload($item)->setWorkingState('completed', 1)->save();
    }, delay: 30);
    $this->assertSame($attempt->id, $waiting->id);
    $this->assertSame(3, $waiting->version);
    $this->assertTrue($this->reload($item)->isIncomplete());
    $this->assertSame(1, $this->reload($item)->get('state')->get('completed')->getCastedValue());
    $this->assertSame([], $this->claims->due());
    $this->assertConflict(fn() => $this->claims->claim($waiting));
    $this->assertConflict(fn() => $this->journal->transition($waiting, ChecklistAttempt::RUNNING, 22));
    $this->now += 30;
    $this->assertSame([$attempt->id], $this->claims->due());
    $next = $this->claims->claim($waiting);
    $this->assertNotSame($claim->token, $next->token);
    $this->assertConflict(fn() => $this->claims->commit($claim, ChecklistAttempt::SUCCEEDED));
    $done = $this->claims->commit($next, ChecklistAttempt::SUCCEEDED, function () use ($item): void {
      $fresh = $this->reload($item);
      $this->assertSame('provider-123', $fresh->get('state')->get('run_id')->getValue());
      $fresh->setOutcome('result', 'Finished')->setComplete()->save();
    });
    $this->assertSame($attempt->id, $done->id);
    $this->assertSame(5, $done->version);
    $this->assertTrue($done->isTerminal());
    $this->assertTrue($this->reload($item)->get('state')->isEmpty());
    $this->assertSame('Finished', $this->reload($item)->get('outcomes')->get('result')->getValue());
    $this->assertSame([], $this->claims->due());
    $this->assertSame([11, 22, 22, 22, 22], array_column($this->journal->history($attempt->id), 'actor'));
  }

  /**
   * New arrivals do not jump ahead of an already-due continuation.
   */
  public function testDueOrder(): void {
    $attempt = $this->journal->create($this->item(), 1, 2, ChecklistAttempt::ACTION);
    $claim = $this->claims->claim($attempt);
    $this->claims->commit($claim, ChecklistAttempt::WAITING, delay: 10);
    $this->now += 20;
    $later = $this->journal->create($this->item(), 1, 2, ChecklistAttempt::ACTION);
    $this->assertSame([$attempt->id, $later->id], $this->claims->due());
    $this->assertSame([$attempt->id], $this->claims->due(1));
  }

  /**
   * Claims exclude duplicate workers but permit independent items to progress.
   */
  public function testIsolation(): void {
    $first = $this->journal->create($this->item(), 1, 2, ChecklistAttempt::ACTION);
    $second = $this->journal->create($this->item(), 1, 3, ChecklistAttempt::ACTION);
    $one = $this->claims->claim($first);
    $two = $this->claims->claim($second);
    $this->assertConflict(fn() => $this->claims->claim($first));
    $this->assertConflict(fn() => $this->journal->transition($one->attempt, ChecklistAttempt::FAILED, 1));
    $wrong = new ChecklistAttemptClaim($two->attempt, $one->token, $one->expires);
    $this->assertConflict(fn() => $this->claims->commit($wrong, ChecklistAttempt::SUCCEEDED));
    $this->claims->commit($one, ChecklistAttempt::WAITING);
    $this->assertSame([$first->id], $this->claims->due());
    $this->assertSame(ChecklistAttempt::RUNNING, $this->journal->load($second->id)->status);
  }

  /**
   * Heartbeats rotate tokens and cannot revive an expired iteration.
   */
  public function testRenewal(): void {
    $attempt = $this->journal->create($this->item(), 1, 2, ChecklistAttempt::ACTION);
    $claim = $this->claims->claim($attempt, 10);
    $same_tick = $this->claims->renew($claim, 10);
    $this->assertSame($claim->expires, $same_tick->expires);
    $this->assertNotSame($claim->token, $same_tick->token);
    $claim = $same_tick;
    $this->now += 5;
    $renewed = $this->claims->renew($claim, 10);
    $this->assertSame(1015, $renewed->expires);
    $this->assertNotSame($claim->token, $renewed->token);
    $this->assertEquals($claim->attempt, $renewed->attempt);
    $this->assertCount(2, $this->journal->history($attempt->id));
    $this->assertConflict(fn() => $this->claims->renew($claim));
    $this->assertConflict(fn() => $this->claims->commit($claim, ChecklistAttempt::WAITING));
    $this->now = $renewed->expires;
    $forged = new ChecklistAttemptClaim($renewed->attempt, $renewed->token, 99999);
    $this->assertConflict(fn() => $this->claims->renew($forged));
    $this->assertConflict(fn() => $this->claims->commit($forged, ChecklistAttempt::SUCCEEDED));
  }

  /**
   * Expiry records failure without automatically repeating external work.
   */
  public function testExpiry(): void {
    $item = $this->item();
    $attempt = $this->journal->create($item, 1, 2, ChecklistAttempt::ACTION);
    $claim = $this->claims->claim($attempt, 10);
    $this->assertConflict(fn() => $this->claims->expire($claim->attempt, 3));
    $this->now = $claim->expires;
    $called = FALSE;
    $this->assertConflict(fn() => $this->claims->commit($claim, ChecklistAttempt::SUCCEEDED, function () use (&$called): void {
      $called = TRUE;
    }));
    $this->assertFalse($called);
    $this->assertSame([], $this->claims->due());
    $failed = $this->claims->expire($claim->attempt, 3);
    $this->assertSame(ChecklistAttempt::FAILED, $failed->status);
    $this->assertSame('provider-123', $this->reload($item)->get('state')->get('run_id')->getValue());
    $this->assertSame(3, $this->journal->history($failed->id)[2]['actor']);
    $this->assertConflict(fn() => $this->claims->expire($claim->attempt, 3));
    $resume = $this->journal->create($item, 3, 2, ChecklistAttempt::ACTION, mode: ChecklistAttempt::RESUME, previous: $failed->id);
    $next = $this->claims->claim($resume);
    $this->assertNotSame($claim->attempt->id, $next->attempt->id);
    $this->assertConflict(fn() => $this->claims->commit($claim, ChecklistAttempt::SUCCEEDED));
  }

  /**
   * Result failures roll back entity writes, claim release and history.
   *
   * @dataProvider rollbackCases
   */
  public function testRollback(bool $expire): void {
    $item = $this->item();
    $attempt = $this->journal->create($item, 1, 2, ChecklistAttempt::ACTION);
    $claim = $this->claims->claim($attempt, 10);
    try {
      $this->claims->commit($claim, ChecklistAttempt::WAITING, function () use ($item, $expire): void {
        $this->reload($item)->setWorkingState('run_id', 'must-roll-back')->save();
        if ($expire) {
          $this->now += 10;
        }
        else {
          throw new \RuntimeException('Application failed');
        }
      });
      $this->fail('The failed result application must roll back.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame($expire ? ChecklistAttemptConflictException::class : \RuntimeException::class, get_class($exception));
      $this->assertSame('provider-123', $this->reload($item)->get('state')->get('run_id')->getValue());
      $this->assertEquals($claim->attempt, $this->journal->load($attempt->id));
      $this->assertCount(2, $this->journal->history($attempt->id));
    }
    if (!$expire) {
      $done = $this->claims->commit($claim, ChecklistAttempt::FAILED);
      $this->assertSame(ChecklistAttempt::FAILED, $done->status);
    }
  }

  /**
   * Provides callback exceptions and expiry while applying results.
   */
  public static function rollbackCases(): array {
    return [[FALSE], [TRUE]];
  }

  /**
   * Caller transactions cannot keep worker claims uncommitted across work.
   */
  public function testOuterTransaction(): void {
    $attempt = $this->journal->create($this->item(), 1, 2, ChecklistAttempt::ACTION);
    $transaction = $this->container->get('database')->startTransaction();
    try {
      $this->claims->claim($attempt);
      $this->fail('Claims inside an outer transaction must be rejected.');
    }
    catch (\LogicException) {
      $this->assertEquals($attempt, $this->journal->load($attempt->id));
    }
    finally {
      $transaction->rollBack();
    }
  }

  /**
   * Existing attempt metadata/history survive an idempotent schema upgrade.
   */
  public function testUpgrade(): void {
    $database = $this->container->get('database');
    $schema = $database->schema();
    $attempt = $this->journal->create($this->item(), 1, 2, ChecklistAttempt::ACTION);
    $history = $this->journal->history($attempt->id);
    $schema->dropIndex('checklist_attempt', 'due');
    foreach (['claim_token', 'claim_expires', 'available'] as $field) {
      $schema->dropField('checklist_attempt', $field);
    }
    $this->container->get('module_handler')->loadInclude('checklist', 'install');
    checklist_update_10003();
    checklist_update_10003();
    $this->assertEquals($attempt, $this->journal->load($attempt->id));
    $this->assertSame($history, $this->journal->history($attempt->id));
    $this->assertSame([$attempt->id], $this->claims->due());
    $this->assertSame(ChecklistAttempt::RUNNING, $this->claims->claim($attempt)->attempt->status);
  }

  /**
   * Asserts stale/duplicate work is rejected before effects can be applied.
   */
  protected function assertConflict(callable $operation): void {
    try {
      $operation();
      $this->fail('The stale or unavailable claim must be rejected.');
    }
    catch (ChecklistAttemptConflictException) {
      $this->addToAssertionCount(1);
    }
  }

}
