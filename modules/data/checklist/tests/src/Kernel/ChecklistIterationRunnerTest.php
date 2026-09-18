<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\checklist\Attempt\ChecklistAttemptDispatchStorageInterface;
use Drupal\checklist\Entity\ChecklistItem;
use Drupal\checklist\Execution\ChecklistIterationScheduler;
use Drupal\checklist_state_test\Plugin\ChecklistItemHandler\Iteration;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests multi-request execution with identity, access and stale-result checks.
 *
 * @group checklist
 */
class ChecklistIterationRunnerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'text', 'filter', 'options', 'entity',
    'checklist', 'checklist_context_test', 'checklist_state_test',
    'checklist_resolver_test', 'plugin_reference', 'typed_data', 'typed_data_plus',
    'typed_data_reference', 'typed_data_context_assignment', 'inline_entity_form',
  ];

  /**
   * Controlled current time, independent of request time.
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
    FieldStorageConfig::create([
      'field_name' => 'work',
      'entity_type' => 'user',
      'type' => 'checklist',
    ])->save();
    FieldConfig::create(['field_name' => 'work', 'entity_type' => 'user', 'bundle' => 'user', 'translatable' => FALSE])->save();
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturnCallback(fn() => $this->now);
    $time->method('getRequestTime')->willReturn(1000);
    $this->container->set('datetime.time', $time);
    $caller = User::create(['name' => 'Caller', 'status' => 1]);
    $caller->save();
    $this->container->get('current_user')->setAccount($caller);
    Iteration::$calls = [];
    Iteration::$during = NULL;
  }

  /**
   * Creates a saved autonomous item, owned/executed by a non-admin user.
   */
  protected function work(array $configuration = [], ?int $executor = NULL, string $name = 'Target'): array {
    $host = User::create([
      'name' => $name,
      'status' => 1,
      'work' => [
        'id' => 'context_test',
        'configuration' => [
          'default_items' => [
            'worker' => [
              'title' => 'Worker',
              'handler' => 'iteration_test',
              'handler_configuration' => $configuration + ['context_mapping' => ['value' => 'checklist:entity.name.value']],
            ],
          ],
        ],
      ],
    ]);
    $host->save();
    $item = $host->work->checklist->getItem('worker');
    $item->save();
    $attempt = $this->container->get('checklist.attempt_journal')->create($item, 1, $executor ?? (int) $host->id(), ChecklistAttempt::ACTION);
    return [$host, $item, $attempt];
  }

  /**
   * Two requests share an attempt and restore state, contexts and identity.
   */
  public function testContinuation(): void {
    [$host, $item, $attempt] = $this->work();
    $runner = $this->container->get('checklist.iteration_runner');
    $waiting = $runner->run($attempt);
    $this->assertSame(ChecklistAttempt::WAITING, $waiting->status);
    $this->assertSame($attempt->id, $waiting->id);
    $this->assertSame('1', (string) $this->container->get('current_user')->id());
    $this->now += 5;
    $done = $runner->run($waiting);
    $this->assertSame(ChecklistAttempt::SUCCEEDED, $done->status);
    $this->assertSame($attempt->id, $done->id);
    $this->assertSame('1', (string) $this->container->get('current_user')->id());
    $this->assertSame([
      [(int) $host->id(), FALSE, NULL, 'Target', $attempt->id],
      [(int) $host->id(), FALSE, 'provider-123', 'Target', $attempt->id],
    ], Iteration::$calls);
    $saved = $this->reload($item);
    $this->assertTrue($saved->isComplete());
    $this->assertTrue($saved->get('state')->isEmpty());
    $this->assertSame('Target', $saved->get('outcomes')->get('result')->getValue());
    $this->expectException(ChecklistAttemptConflictException::class);
    $runner->run($waiting);
  }

  /**
   * Explicit failure saves intermediate state without publishing a state dump.
   */
  public function testFailure(): void {
    [, $item, $attempt] = $this->work(['fail' => TRUE]);
    $failed = $this->container->get('checklist.iteration_runner')->run($attempt);
    $this->assertSame(ChecklistAttempt::FAILED, $failed->status);
    $saved = $this->reload($item);
    $this->assertTrue($saved->isFailed());
    $this->assertSame('failed-run', $saved->get('state')->get('run_id')->getValue());
    $this->assertTrue($saved->get('outcomes')->isEmpty());
  }

  /**
   * Handler exceptions retain state and restore the caller without raw history.
   */
  public function testException(): void {
    [, $item, $attempt] = $this->work(['throw' => TRUE]);
    $item->setWorkingState('run_id', 'retained')->save();
    try {
      $this->container->get('checklist.iteration_runner')->run($attempt);
      $this->fail('The original handler exception must propagate.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('Secret provider payload', $exception->getMessage());
      $this->assertSame('1', (string) $this->container->get('current_user')->id());
      $this->assertTrue($this->reload($item)->isFailed());
      $this->assertSame('retained', $this->reload($item)->get('state')->get('run_id')->getValue());
      $events = $this->container->get('checklist.attempt_journal')->history($attempt->id);
      $this->assertSame('Automatic iteration failed.', $events[2]['reason']);
    }
  }

  /**
   * Changes during a provider call prevent stale or unauthorized result writes.
   *
   * @dataProvider inFlightChanges
   */
  public function testChangedDuringRun(string $change): void {
    [$host, $item, $attempt] = $this->work();
    Iteration::$during = function () use ($change, $host, $item): void {
      if ($change === 'blocked') {
        User::load($host->id())->block()->save();
      }
      elseif ($change === 'field_access') {
        $this->container->get('state')->set('checklist_resolver_test.denied_field_operations', ['work' => ['edit']]);
      }
      elseif ($change === 'state') {
        $this->reload($item)->setWorkingState('run_id', 'other-writer')->save();
      }
      elseif ($change === 'context') {
        User::load($host->id())->setUsername('Changed')->save();
      }
      else {
        $this->now += 300;
      }
    };
    try {
      $this->container->get('checklist.iteration_runner')->run($attempt);
      $this->fail('An in-flight change must prevent applying the result.');
    }
    catch (\Exception $exception) {
      $this->assertInstanceOf(in_array($change, ['blocked', 'field_access'], TRUE) ? AccessDeniedHttpException::class : ChecklistAttemptConflictException::class, $exception);
      $this->assertCount(1, Iteration::$calls);
      $this->assertSame('1', (string) $this->container->get('current_user')->id());
      $saved = $this->reload($item);
      $this->assertTrue($saved->isIncomplete());
      $this->assertNull($saved->get('state')->get('completed')->getValue());
      $this->assertSame($change === 'state' ? 'other-writer' : NULL, $saved->get('state')->get('run_id')->getValue());
      $current = $this->container->get('checklist.attempt_journal')->load($attempt->id);
      $this->assertSame($change === 'expiry' ? ChecklistAttempt::RUNNING : ChecklistAttempt::FAILED, $current->status);
    }
  }

  /**
   * Provides account, access, input, state and claim changes.
   */
  public static function inFlightChanges(): array {
    return [['blocked'], ['field_access'], ['state'], ['context'], ['expiry']];
  }

  /**
   * Results based on a sibling outcome cannot overwrite a changed context.
   */
  public function testSiblingOutcomeChange(): void {
    [$host, $item, $attempt] = $this->work(['context_mapping' => ['value' => 'item:source:result']]);
    $source = ChecklistItem::create([
      'checklist_type' => 'context_test',
      'name' => 'source',
      'title' => 'Source',
      'handler' => ['id' => 'state_test', 'configuration' => []],
      'checklist' => ['entity' => $host, 'checklist_key' => 'work'],
    ]);
    $source->setOutcome('result', 'Before')->save();
    Iteration::$during = function () use ($source): void {
      // Simulate another request without invalidating this worker's item cache.
      $this->container->get('database')->update('checklist_item__outcomes')
        ->fields(['outcomes_value' => 'After'])
        ->condition('entity_id', $source->id())->execute();
    };
    try {
      $this->container->get('checklist.iteration_runner')->run($attempt);
      $this->fail('Changed outcome inputs must reject the result.');
    }
    catch (ChecklistAttemptConflictException) {
      $this->assertSame('Before', Iteration::$calls[0][3]);
      $this->assertSame('After', $this->reload($source)->get('outcomes')->get('result')->getValue());
      $this->assertTrue($this->reload($item)->get('state')->isEmpty());
      $this->assertSame(ChecklistAttempt::FAILED, $this->container->get('checklist.attempt_journal')->load($attempt->id)->status);
    }
  }

  /**
   * Denied fields cannot invoke a handler or acquire a claim.
   */
  public function testDeniedBeforeRun(): void {
    [, , $attempt] = $this->work();
    $this->container->get('state')->set('checklist_resolver_test.denied_field_operations', ['work' => ['edit']]);
    try {
      $this->container->get('checklist.iteration_runner')->run($attempt);
      $this->fail('Denied work must not run.');
    }
    catch (AccessDeniedHttpException) {
      $this->assertSame([], Iteration::$calls);
      $this->assertEquals($attempt, $this->container->get('checklist.attempt_journal')->load($attempt->id));
      $this->assertSame('1', (string) $this->container->get('current_user')->id());
    }
  }

  /**
   * Invalid handler output cannot partly update state or outcomes.
   *
   * @dataProvider invalidResults
   */
  public function testInvalidOutput(string $mode): void {
    [, $item, $attempt] = $this->work([$mode => TRUE]);
    try {
      $this->container->get('checklist.iteration_runner')->run($attempt);
      $this->fail('Undeclared state must be rejected.');
    }
    catch (\InvalidArgumentException) {
      $this->assertTrue($this->reload($item)->get('state')->isEmpty());
      $this->assertSame(ChecklistAttempt::FAILED, $this->container->get('checklist.attempt_journal')->load($attempt->id)->status);
      $this->assertSame('1', (string) $this->container->get('current_user')->id());
    }
  }

  /**
   * Provides undeclared names and invalid typed values after a valid update.
   */
  public static function invalidResults(): array {
    return [['invalid'], ['invalid_value']];
  }

  /**
   * Native gates and missing required values prevent work without a claim.
   *
   * @dataProvider blockedConfigurations
   */
  public function testGates(array $configuration): void {
    [, , $attempt] = $this->work($configuration);
    try {
      $this->container->get('checklist.iteration_runner')->run($attempt);
      $this->fail('Blocked work must not run.');
    }
    catch (\DomainException) {
      $this->assertSame([], Iteration::$calls);
      $this->assertEquals($attempt, $this->container->get('checklist.attempt_journal')->load($attempt->id));
      $this->assertSame('1', (string) $this->container->get('current_user')->id());
    }
  }

  /**
   * Provides native condition gates and a missing runtime value.
   */
  public static function blockedConfigurations(): array {
    return [
      [['conditions' => ['applicability' => ['id' => 'condition_constant:false']]]],
      [['conditions' => ['actionability' => ['id' => 'condition_constant:false']]]],
      [['context_mapping' => ['value' => 'item:worker:result']]],
    ];
  }

  /**
   * Cron's administrator identity cannot authorize a less privileged executor.
   */
  public function testExecutorAccess(): void {
    $executor = User::create(['name' => 'Other', 'status' => 1]);
    $executor->save();
    [, , $attempt] = $this->work(executor: (int) $executor->id());
    try {
      $this->container->get('checklist.iteration_runner')->run($attempt);
      $this->fail("The executor cannot edit somebody else's profile.");
    }
    catch (AccessDeniedHttpException) {
      $this->assertSame([], Iteration::$calls);
      $this->assertEquals($attempt, $this->container->get('checklist.attempt_journal')->load($attempt->id));
      $this->assertSame('1', (string) $this->container->get('current_user')->id());
    }
  }

  /**
   * Legacy processing never invokes an iterative handler's synchronous action.
   */
  public function testLegacyProcessor(): void {
    [$host, $item] = $this->work();
    $this->assertFalse($host->work->checklist->process());
    $this->assertSame([], Iteration::$calls);
    $this->expectException(\LogicException::class);
    $item->action();
  }

  /**
   * Cron dispatches bounded iterations with delay and duplicate suppression.
   */
  public function testQueueContinuation(): void {
    [, $item, $attempt] = $this->work();
    $scheduler = $this->container->get('checklist.iteration_scheduler');
    $queue = $this->container->get('queue')->get(ChecklistIterationScheduler::QUEUE);
    $worker = $this->container->get('plugin.manager.queue_worker')->createInstance(ChecklistIterationScheduler::QUEUE);
    checklist_cron();
    $this->assertSame(1, $queue->numberOfItems());
    $this->assertSame(0, $scheduler->dispatch());
    $message = $queue->claimItem();
    $this->assertSame(['attempt' => $attempt->id, 'version' => 1], $message->data);
    $worker->processItem($message->data);
    $worker->processItem($message->data);
    $queue->deleteItem($message);
    $this->assertCount(1, Iteration::$calls);
    $this->assertSame(0, $scheduler->dispatch());
    $this->now += 5;
    $this->assertSame(1, $scheduler->dispatch());
    $next = $queue->claimItem();
    $this->assertSame(['attempt' => $attempt->id, 'version' => 3], $next->data);
    $worker->processItem($next->data);
    $queue->deleteItem($next);
    $worker->processItem($message->data);
    $worker->processItem($next->data);
    $this->assertCount(2, Iteration::$calls);
    $this->assertTrue($this->reload($item)->isComplete());
    $this->assertSame(0, $scheduler->dispatch());
    $this->assertSame('1', (string) $this->container->get('current_user')->id());
  }

  /**
   * Lost messages are redelivered after expiry within the same attempt.
   */
  public function testLostDelivery(): void {
    [, , $attempt] = $this->work();
    $scheduler = $this->container->get('checklist.iteration_scheduler');
    $queue = $this->container->get('queue')->get(ChecklistIterationScheduler::QUEUE);
    $this->assertSame(1, $scheduler->dispatch());
    $message = $queue->claimItem();
    $queue->deleteItem($message);
    $this->now += 299;
    $this->assertSame(0, $scheduler->dispatch());
    $this->now++;
    $this->assertSame(1, $scheduler->dispatch());
    $this->assertSame($message->data, $queue->claimItem()->data);
    $this->assertEquals($attempt, $this->container->get('checklist.attempt_journal')->load($attempt->id));
    $this->assertCount(1, $this->container->get('checklist.attempt_journal')->history($attempt->id));
    $this->assertSame([], Iteration::$calls);
  }

  /**
   * Blocked executors do not starve other work and can become eligible later.
   */
  public function testQueueAccessAndFairness(): void {
    [$host, , $blocked] = $this->work();
    $host->block()->save();
    $this->now++;
    [, , $other] = $this->work(name: 'Other');
    $scheduler = $this->container->get('checklist.iteration_scheduler');
    $queue = $this->container->get('queue')->get(ChecklistIterationScheduler::QUEUE);
    $worker = $this->container->get('plugin.manager.queue_worker')->createInstance(ChecklistIterationScheduler::QUEUE);
    $this->assertSame(1, $scheduler->dispatch(1));
    $message = $queue->claimItem();
    $this->assertSame($blocked->id, $message->data['attempt']);
    $worker->processItem($message->data);
    $queue->deleteItem($message);
    $this->assertSame([], Iteration::$calls);
    $this->assertEquals($blocked, $this->container->get('checklist.attempt_journal')->load($blocked->id));
    $this->assertSame(1, $scheduler->dispatch(1));
    $this->assertSame($other->id, $queue->claimItem()->data['attempt']);
    $this->assertSame(0, $scheduler->dispatch());
    $host->activate()->save();
    $this->now += 300;
    $this->assertSame(1, $scheduler->dispatch(1));
    // Run the new delivery; the old payload is also safe at this same version.
    $worker->processItem($message->data);
    $this->assertCount(1, Iteration::$calls);
    $this->assertSame('1', (string) $this->container->get('current_user')->id());
  }

  /**
   * Transport rejection is retried later without exposing raw error data.
   *
   * @dataProvider transportFailures
   */
  public function testTransportFailure(bool $throws): void {
    [, , $attempt] = $this->work();
    $queue = $this->createMock(QueueInterface::class);
    $create = $queue->expects($this->exactly(2))->method('createItem');
    if ($throws) {
      $create->willThrowException(new \RuntimeException('Secret transport payload'));
    }
    else {
      $create->willReturn(FALSE);
    }
    $factory = $this->createMock(QueueFactory::class);
    $factory->method('get')->with(ChecklistIterationScheduler::QUEUE)->willReturn($queue);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->exactly(2))->method('error')->with(
      'Checklist iteration delivery failed for attempt {attempt}; scheduling will retry.',
      ['attempt' => $attempt->id],
    );
    $scheduler = new ChecklistIterationScheduler($this->container->get('checklist.attempt_dispatch_storage'), $factory, $logger);
    $this->assertSame(0, $scheduler->dispatch());
    $this->assertSame(0, $scheduler->dispatch());
    $this->now += 300;
    $this->assertSame(0, $scheduler->dispatch());
    $this->assertEquals($attempt, $this->container->get('checklist.attempt_journal')->load($attempt->id));
  }

  /**
   * Provides Queue API failure modes.
   */
  public static function transportFailures(): array {
    return [[FALSE], [TRUE]];
  }

  /**
   * Failed and expired running executions are never automatically replayed.
   *
   * @dataProvider executionFailures
   */
  public function testQueueExecutionFailure(bool $expired): void {
    [, , $attempt] = $this->work($expired ? [] : ['throw' => TRUE]);
    if ($expired) {
      Iteration::$during = function (): void {
        $this->now += 300;
      };
    }
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($expired ? $this->never() : $this->once())->method('warning')->with(
      'Checklist iteration could not complete for attempt {attempt}; inspect its current status before retrying.',
      ['attempt' => $attempt->id],
    );
    $this->container->set('logger.channel.checklist', $logger);
    $scheduler = $this->container->get('checklist.iteration_scheduler');
    $queue = $this->container->get('queue')->get(ChecklistIterationScheduler::QUEUE);
    $worker = $this->container->get('plugin.manager.queue_worker')->createInstance(ChecklistIterationScheduler::QUEUE);
    $this->assertSame(1, $scheduler->dispatch());
    $message = $queue->claimItem();
    $worker->processItem($message->data);
    $queue->deleteItem($message);
    $worker->processItem($message->data);
    $this->now += 600;
    $this->assertSame(0, $scheduler->dispatch());
    $this->assertCount(1, Iteration::$calls);
    $current = $this->container->get('checklist.attempt_journal')->load($attempt->id);
    $this->assertSame($expired ? ChecklistAttempt::RUNNING : ChecklistAttempt::FAILED, $current->status);
    $this->assertSame('1', (string) $this->container->get('current_user')->id());
  }

  /**
   * Provides handler failure and claim expiry cases.
   */
  public static function executionFailures(): array {
    return [[FALSE], [TRUE]];
  }

  /**
   * A scan cannot send work from an uncommitted transaction.
   */
  public function testDispatchTransaction(): void {
    $transaction = $this->container->get('database')->startTransaction();
    $this->work();
    try {
      $this->container->get('checklist.iteration_scheduler')->dispatch();
      $this->fail('Uncommitted work must not be dispatched.');
    }
    catch (\LogicException) {
      $this->assertSame([], Iteration::$calls);
    }
    finally {
      $transaction->rollBack();
    }
    $this->assertSame(0, $this->container->get('checklist.iteration_scheduler')->dispatch());
  }

  /**
   * An upgrade preserves attempt history and makes old due work dispatchable.
   */
  public function testDeliveryUpgrade(): void {
    [, , $attempt] = $this->work();
    $database = $this->container->get('database');
    $journal = $this->container->get('checklist.attempt_journal');
    $history = $journal->history($attempt->id);
    $database->schema()->dropField('checklist_attempt', 'dispatch_expires');
    $this->container->get('module_handler')->loadInclude('checklist', 'install');
    checklist_update_10004();
    checklist_update_10004();
    $this->assertEquals($attempt, $journal->load($attempt->id));
    $this->assertSame($history, $journal->history($attempt->id));
    $this->assertSame(1, $this->container->get('checklist.iteration_scheduler')->dispatch());
  }

  /**
   * Another scheduler observes the reservation before enqueue finishes.
   */
  public function testCompetingDispatch(): void {
    [, , $attempt] = $this->work();
    $factory = $this->createMock(QueueFactory::class);
    $queue = $this->createMock(QueueInterface::class);
    $factory->method('get')->willReturn($queue);
    $scheduler = new ChecklistIterationScheduler($this->container->get('checklist.attempt_dispatch_storage'), $factory, $this->createMock(LoggerInterface::class));
    $queue->expects($this->once())->method('createItem')->willReturnCallback(function ($data) use ($scheduler, $attempt) {
      $this->assertSame(['attempt' => $attempt->id, 'version' => 1], $data);
      $this->assertFalse($this->container->get('database')->inTransaction());
      $this->assertSame(0, $scheduler->dispatch());
      return 1;
    });
    $this->assertSame(1, $scheduler->dispatch());
  }

  /**
   * Even infrequent cron scans move past previously dispatched blocked work.
   */
  public function testInfrequentDispatchFairness(): void {
    [, , $first] = $this->work();
    $scheduler = $this->container->get('checklist.iteration_scheduler');
    $queue = $this->container->get('queue')->get(ChecklistIterationScheduler::QUEUE);
    $this->assertSame(1, $scheduler->dispatch(1));
    $message = $queue->claimItem();
    $this->assertSame($first->id, $message->data['attempt']);
    $queue->deleteItem($message);
    $this->now += 600;
    [, , $second] = $this->work(name: 'Second');
    $this->assertSame(1, $scheduler->dispatch(1));
    $this->assertSame($second->id, $queue->claimItem()->data['attempt']);
  }

  /**
   * Cancellation, other paths and successor intents prevent dispatch.
   */
  public function testDispatchScope(): void {
    [, $item, $attempt] = $this->work();
    $journal = $this->container->get('checklist.attempt_journal');
    $cancelled = $journal->transition($attempt, ChecklistAttempt::CANCELLED, 1);
    $scheduler = $this->container->get('checklist.iteration_scheduler');
    $this->assertSame(0, $scheduler->dispatch());
    $journal->create($item, 1, 1, ChecklistAttempt::ACTION, mode: ChecklistAttempt::FRESH, previous: $cancelled->id);
    $this->assertSame(0, $scheduler->dispatch());
    foreach ([ChecklistAttempt::ACTION_FORM, ChecklistAttempt::ACTION_OPERATION] as $path) {
      $other = ChecklistItem::create(['checklist_type' => 'context_test']);
      $journal->create($other, 1, 1, $path, $path === ChecklistAttempt::ACTION_OPERATION ? 'choose' : NULL);
    }
    $this->assertSame(0, $scheduler->dispatch());
    $worker = $this->container->get('plugin.manager.queue_worker')->createInstance(ChecklistIterationScheduler::QUEUE);
    $worker->processItem(['attempt' => $attempt->id, 'version' => $attempt->version]);
    $worker->processItem(['attempt' => 'missing', 'version' => 1]);
    $worker->processItem(['attempt' => $attempt->id, 'version' => '1']);
    $worker->processItem(NULL);
    $this->assertSame([], Iteration::$calls);
  }

  /**
   * Replacing dispatch storage does not require changing scheduler or worker.
   */
  public function testReplaceDispatchStorage(): void {
    [, , $attempt] = $this->work();
    $storage = $this->createMock(ChecklistAttemptDispatchStorageInterface::class);
    $storage->expects($this->once())->method('reserveDue')->with(7)->willReturn([
      ['attempt' => $attempt->id, 'version' => $attempt->version],
    ]);
    $this->container->set('checklist.attempt_dispatch_storage', $storage);
    $this->assertSame(1, $this->container->get('checklist.iteration_scheduler')->dispatch(7));
    $queue = $this->container->get('queue')->get(ChecklistIterationScheduler::QUEUE);
    $message = $queue->claimItem();
    $this->assertSame(['attempt' => $attempt->id, 'version' => 1], $message->data);
    $worker = $this->container->get('plugin.manager.queue_worker')->createInstance(ChecklistIterationScheduler::QUEUE);
    $worker->processItem($message->data);
    $this->assertCount(1, Iteration::$calls);
    $this->assertSame(ChecklistAttempt::WAITING, $this->container->get('checklist.attempt_journal')->load($attempt->id)->status);
  }

  /**
   * Storage rejects unbounded scans before making any delivery reservations.
   */
  public function testDispatchStorageLimits(): void {
    [, , $attempt] = $this->work();
    $storage = $this->container->get('checklist.attempt_dispatch_storage');
    foreach ([0, 101] as $limit) {
      try {
        $storage->reserveDue($limit);
        $this->fail('Invalid batch limits must be rejected.');
      }
      catch (\InvalidArgumentException) {
        $this->addToAssertionCount(1);
      }
    }
    $this->assertSame([['attempt' => $attempt->id, 'version' => 1]], $storage->reserveDue(1));
    $this->assertSame([], $storage->reserveDue(1));
  }

  /**
   * Reloads item state after a commit or rollback.
   */
  protected function reload($item) {
    return $this->container->get('entity_type.manager')->getStorage('checklist_item')->loadUnchanged($item->id());
  }

}
