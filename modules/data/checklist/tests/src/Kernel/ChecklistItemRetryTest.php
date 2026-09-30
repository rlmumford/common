<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\checklist\Execution\ChecklistItemIterationScheduler;
use Drupal\checklist\Execution\ChecklistItemNotReadyException;
use Drupal\checklist_state_test\Plugin\ChecklistItemHandler\Iteration;
use Drupal\Core\Entity\EntityStorageException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests explicit retries without losing state, history or execution fences.
 *
 * @group checklist
 */
class ChecklistItemRetryTest extends ChecklistItemExecutionTestBase {

  /**
   * Fails after a checkpoint, retaining state and an existing outcome.
   */
  protected function failedWork(): array {
    [$host, $item, $attempt] = $this->work();
    $item->setWorkingState('run_id', 'existing-run');
    $item->setWorkingState('completed', 1);
    $item->setOutcome('result', 'Existing outcome')->save();
    Iteration::$during = static function (): void {
      throw new \RuntimeException('Provider unavailable');
    };
    try {
      $this->container->get('checklist.item_executor')->run($attempt);
      $this->fail('The provider must fail.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('Provider unavailable', $exception->getMessage());
    }
    Iteration::$during = NULL;
    Iteration::$calls = [];
    return [$host, $item, $this->container->get('checklist.attempt_journal')->latest($item), $attempt];
  }

  /**
   * Both modes create separate attempts and execute through the existing queue.
   *
   * @dataProvider retryModes
   */
  public function testRetryThroughQueue(string $mode): void {
    [, $item, $failed] = $this->failedWork();
    $journal = $this->container->get('checklist.attempt_journal');
    $history = $journal->history($failed->id);
    $executor = $this->container->get('checklist.item_executor');
    $this->assertEquals($failed, $executor->submit($item));
    $next = $executor->retry($item, $failed, $mode, TRUE);
    $this->assertSame(ChecklistAttempt::QUEUED, $next->status);
    $this->assertNotSame($failed->id, $next->id);
    $this->assertSame($failed->id, $next->previous);
    $this->assertSame($mode, $next->mode);
    $this->assertSame(1, $next->initiator);
    $this->assertSame(1, $next->executor);
    $this->assertSame([], Iteration::$calls);
    $saved = $this->reload($item);
    $this->assertTrue($saved->isIncomplete());
    $this->assertTrue($saved->get('failure_method')->isEmpty());
    $this->assertSame($mode === ChecklistAttempt::RESUME ? 'existing-run' : NULL, $saved->get('state')->get('run_id')->getValue());
    $this->assertSame('Existing outcome', $saved->get('outcomes')->get('result')->getValue());
    $this->assertEquals($failed, $journal->load($failed->id));
    $this->assertSame($history, $journal->history($failed->id));
    // A duplicate request may neither create a successor nor erase its state.
    try {
      $executor->retry($item, $failed, ChecklistAttempt::FRESH);
      $this->fail('The previous attempt is no longer current.');
    }
    catch (ChecklistAttemptConflictException) {
      $this->assertEquals($next, $journal->latest($item));
      $this->assertEquals($saved->toArray(), $this->reload($item)->toArray());
    }
    $scheduler = $this->container->get('checklist.item_iteration_scheduler');
    $queue = $this->container->get('queue')->get(ChecklistItemIterationScheduler::QUEUE);
    $worker = $this->container->get('plugin.manager.queue_worker')->createInstance(ChecklistItemIterationScheduler::QUEUE);
    $this->assertSame(1, $scheduler->dispatch());
    $message = $queue->claimItem();
    $this->assertSame($next->id, $message->data['attempt']);
    $worker->processItem($message->data);
    $worker->processItem($message->data);
    $queue->deleteItem($message);
    $worker->processItem(['attempt' => $failed->id, 'version' => $failed->version]);
    $this->assertCount(1, Iteration::$calls);
    $this->assertSame(1, Iteration::$calls[0][0]);
    $this->assertFalse(Iteration::$calls[0][1]);
    if ($mode === ChecklistAttempt::FRESH) {
      $this->assertSame(ChecklistAttempt::WAITING, $journal->latest($item)->status);
      $this->now += 5;
      $this->assertSame(1, $scheduler->dispatch());
      $message = $queue->claimItem();
      $worker->processItem($message->data);
      $queue->deleteItem($message);
    }
    $this->assertSame(ChecklistAttempt::SUCCEEDED, $journal->latest($item)->status);
    $this->assertTrue($this->reload($item)->isComplete());
    $this->assertTrue($this->reload($item)->get('state')->isEmpty());
    $this->assertSame($history, $journal->history($failed->id));
  }

  /**
   * Explicit modes have distinct working-state semantics.
   */
  public static function retryModes(): array {
    return [[ChecklistAttempt::RESUME], [ChecklistAttempt::FRESH]];
  }

  /**
   * Inline retries commit their reset before invoking external work.
   */
  public function testInlineRetry(): void {
    [$host, $item, $failed] = $this->failedWork();
    $this->container->get('current_user')->setAccount($host);
    $next = $this->container->get('checklist.item_executor')->retry($item, $failed, ChecklistAttempt::RESUME);
    $this->assertSame(ChecklistAttempt::SUCCEEDED, $next->status);
    $this->assertSame((int) $host->id(), $next->executor);
    $this->assertFalse(Iteration::$calls[0][1]);
    $this->assertSame((string) $host->id(), (string) $this->container->get('current_user')->id());
  }

  /**
   * An inline failure remains a separate durable attempt with retained state.
   */
  public function testRetryFailsAgain(): void {
    [, $item, $failed] = $this->failedWork();
    $journal = $this->container->get('checklist.attempt_journal');
    $history = $journal->history($failed->id);
    Iteration::$during = static function (): void {
      throw new \RuntimeException('Still unavailable');
    };
    try {
      $this->container->get('checklist.item_executor')->retry($item, $failed, ChecklistAttempt::RESUME);
      $this->fail('The retried provider must fail.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('Still unavailable', $exception->getMessage());
      $next = $journal->latest($item);
      $this->assertNotSame($failed->id, $next->id);
      $this->assertSame($failed->id, $next->previous);
      $this->assertSame(ChecklistAttempt::FAILED, $next->status);
      $this->assertSame($history, $journal->history($failed->id));
      $this->assertTrue($this->reload($item)->isFailed());
      $this->assertSame('existing-run', $this->reload($item)->get('state')->get('run_id')->getValue());
      $this->assertSame('1', (string) $this->container->get('current_user')->id());
    }
  }

  /**
   * The reset and successor are rolled back together, with no provider call.
   */
  public function testOuterRollback(): void {
    [, $item, $failed] = $this->failedWork();
    $journal = $this->container->get('checklist.attempt_journal');
    $transaction = $this->container->get('database')->startTransaction();
    $next = $this->container->get('checklist.item_executor')->retry($item, $failed, ChecklistAttempt::FRESH);
    $this->assertSame(ChecklistAttempt::QUEUED, $next->status);
    $this->assertTrue($this->reload($item)->get('state')->isEmpty());
    $this->assertSame([], Iteration::$calls);
    $transaction->rollBack();
    unset($transaction);
    // Discard the entity cached by our read inside the rolled-back transaction.
    $this->container->get('entity_type.manager')->getStorage('checklist_item')->resetCache([$item->id()]);
    $this->assertEquals($failed, $journal->latest($item));
    $this->assertNull($journal->load($next->id));
    $this->assertSame([], $journal->history($next->id));
    $this->assertTrue($this->reload($item)->isFailed());
    $this->assertSame('existing-run', $this->reload($item)->get('state')->get('run_id')->getValue());
    $this->assertSame(0, $this->container->get('checklist.item_iteration_scheduler')->dispatch());
  }

  /**
   * An item-save failure cannot leave a queued successor or clear state.
   */
  public function testSaveFailureRollback(): void {
    [, $item, $failed] = $this->failedWork();
    $journal = $this->container->get('checklist.attempt_journal');
    $before = $this->reload($item)->toArray();
    $this->container->get('state')->set('checklist_resolver_test.fail_item_save', TRUE);
    try {
      $this->container->get('checklist.item_executor')->retry($item, $failed, ChecklistAttempt::FRESH);
      $this->fail('The item save must fail.');
    }
    catch (EntityStorageException $exception) {
      $this->assertStringContainsString('Test item save failure', $exception->getMessage());
      $this->assertEquals($failed, $journal->latest($item));
      $this->assertEquals($before, $this->reload($item)->toArray());
      $this->assertSame([], Iteration::$calls);
      $this->assertSame(0, $this->container->get('checklist.item_iteration_scheduler')->dispatch());
    }
  }

  /**
   * Fresh permissions and gates reject retries without altering the failure.
   *
   * @dataProvider retryBlocks
   */
  public function testBlockedRetry(string $block): void {
    [$host, $item, $failed] = $this->failedWork();
    if ($block === 'field') {
      $this->container->get('state')->set('checklist_resolver_test.denied_field_operations', ['work' => ['edit']]);
    }
    elseif ($block === 'item') {
      $this->container->get('state')->set('checklist_resolver_test.denied_iteration_items', ['worker']);
    }
    elseif ($block === 'account') {
      $this->container->get('current_user')->setAccount($host);
      $host->block()->save();
    }
    else {
      $saved = $this->reload($item);
      $configuration = $saved->getHandler()->getConfiguration();
      $configuration['conditions']['actionability'] = ['id' => 'condition_constant:false'];
      $saved->getHandler()->setConfiguration($configuration);
      $saved->save();
    }
    $before = $this->reload($item)->toArray();
    $uid = $this->container->get('current_user')->id();
    try {
      $this->container->get('checklist.item_executor')->retry($item, $failed, ChecklistAttempt::FRESH);
      $this->fail('Unavailable work must not be retried.');
    }
    catch (AccessDeniedHttpException | ChecklistItemNotReadyException) {
      $this->assertEquals($failed, $this->container->get('checklist.attempt_journal')->latest($item));
      $this->assertEquals($before, $this->reload($item)->toArray());
      $this->assertSame([], Iteration::$calls);
      $this->assertSame($uid, $this->container->get('current_user')->id());
    }
  }

  /**
   * Supplies current permission and actionability failures.
   */
  public static function retryBlocks(): array {
    return [['field'], ['item'], ['account'], ['gate']];
  }

  /**
   * Foreign/stale snapshots and active attempts cannot authorize a retry.
   */
  public function testInvalidPredecessors(): void {
    [, $item, $failed, $stale] = $this->failedWork();
    [, , $other] = $this->work(name: 'Other');
    $journal = $this->container->get('checklist.attempt_journal');
    $executor = $this->container->get('checklist.item_executor');
    foreach ([$other, $stale] as $invalid) {
      try {
        $executor->retry($item, $invalid, ChecklistAttempt::RESUME);
        $this->fail('An unrelated or stale attempt must not authorize retry.');
      }
      catch (ChecklistAttemptConflictException) {
        $this->assertEquals($failed, $journal->latest($item));
      }
    }
    $next = $executor->retry($item, $failed, ChecklistAttempt::RESUME, TRUE);
    try {
      $executor->retry($item, $next, ChecklistAttempt::FRESH);
      $this->fail('A queued attempt must not be restarted.');
    }
    catch (\DomainException) {
      $this->assertEquals($next, $journal->latest($item));
    }
    $this->reload($item)->setComplete()->save();
    $next = $journal->transition($next, ChecklistAttempt::RUNNING, 1);
    $next = $journal->transition($next, ChecklistAttempt::FAILED, 1);
    try {
      $executor->retry($item, $next, ChecklistAttempt::FRESH);
      $this->fail('Retry must not reopen a completed item.');
    }
    catch (\DomainException) {
      $this->assertEquals($next, $journal->latest($item));
      $this->assertTrue($this->reload($item)->isComplete());
    }
    $this->assertSame([], Iteration::$calls);
  }

}
