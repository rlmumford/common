<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\checklist\Attempt\ChecklistAttemptJournal;
use Drupal\checklist\Entity\ChecklistItem;
use Drupal\checklist\Execution\ChecklistItemIterationScheduler;
use Drupal\checklist_state_test\Plugin\ChecklistItemHandler\Iteration;
use Drupal\Core\Session\UserSession;
use Drupal\user\Entity\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests caller-authorized submission and its processor/worker integration.
 *
 * @group checklist
 */
class ChecklistItemSubmissionTest extends ChecklistItemExecutionTestBase {

  /**
   * Processing starts inline and a yielded continuation finishes in a worker.
   */
  public function testProcessToCompletion(): void {
    [$host, $item] = $this->work(record_attempt: FALSE);
    $this->container->get('current_user')->setAccount($host);
    $this->assertFalse($host->work->checklist->process());
    $journal = $this->container->get('checklist.attempt_journal');
    $attempt = $journal->latest($item);
    $this->assertSame(ChecklistAttempt::WAITING, $attempt->status);
    $this->assertSame((int) $host->id(), $attempt->initiator);
    $this->assertSame((int) $host->id(), $attempt->executor);
    $this->assertCount(1, Iteration::$calls);
    $this->assertFalse($host->work->checklist->process());
    $this->assertEquals($attempt, $journal->latest($item));
    $this->assertCount(3, $journal->history($attempt->id));
    $queue = $this->container->get('queue')->get(ChecklistItemIterationScheduler::QUEUE);
    $this->assertSame(0, $queue->numberOfItems());
    // The scheduler's ambient account never becomes the recorded executor.
    $this->container->get('current_user')->setAccount(User::load(1));
    $worker = $this->container->get('plugin.manager.queue_worker')->createInstance(ChecklistItemIterationScheduler::QUEUE);
    $this->now += 5;
    checklist_cron();
    $message = $queue->claimItem();
    $worker->processItem($message->data);
    $queue->deleteItem($message);
    $this->assertSame(ChecklistAttempt::SUCCEEDED, $journal->latest($item)->status);
    $this->assertSame('1', (string) $this->container->get('current_user')->id());

    $this->assertCount(2, Iteration::$calls);
    $this->assertSame((int) $host->id(), Iteration::$calls[0][0]);
    $this->assertSame((int) $host->id(), Iteration::$calls[1][0]);
    $this->assertTrue($this->reload($item)->isComplete());
    $fresh_host = $this->container->get('entity_type.manager')->getStorage('user')->loadUnchanged($host->id());
    $this->assertTrue($fresh_host->work->checklist->process());
    $this->assertSame($attempt->id, $journal->latest($item)->id);
  }

  /**
   * Temporary gate/context blocks do not create speculative attempts.
   *
   * @dataProvider blockedConfigurations
   */
  public function testBlockedSubmission(array $configuration): void {
    [, $item] = $this->work($configuration, record_attempt: FALSE);
    $this->assertNull($this->container->get('checklist.item_executor')->submit($item, TRUE));
    $this->assertNull($this->container->get('checklist.attempt_journal')->latest($item));
    $this->assertSame([], Iteration::$calls);
    $this->assertSame('1', (string) $this->container->get('current_user')->id());
    $item->set('handler', [
      'id' => 'iteration_test',
      'configuration' => ['context_mapping' => ['value' => 'checklist:entity.name.value']],
    ])->save();
    $this->assertSame(ChecklistAttempt::QUEUED, $this->container->get('checklist.item_executor')->submit($item, TRUE)->status);
  }

  /**
   * Supplies applicability, actionability and missing context blocks.
   */
  public static function blockedConfigurations(): array {
    return [
      [['conditions' => ['applicability' => ['id' => 'condition_constant:false']]]],
      [['conditions' => ['actionability' => ['id' => 'condition_constant:false']]]],
      [['context_mapping' => ['value' => 'item:worker:result']]],
    ];
  }

  /**
   * An existing attempt cannot be rebound or implicitly retried after failure.
   */
  public function testExistingFailure(): void {
    [$host, $item, $attempt] = $this->work(['fail' => TRUE]);
    $submitter = $this->container->get('checklist.item_executor');
    $this->assertEquals($attempt, $submitter->submit($item, TRUE));
    $this->assertSame((int) $host->id(), $attempt->executor);
    $failed = $this->container->get('checklist.item_executor')->run($attempt);
    $history = $this->container->get('checklist.attempt_journal')->history($attempt->id);
    $this->assertEquals($failed, $submitter->submit($item, TRUE));
    $fresh_host = $this->container->get('entity_type.manager')->getStorage('user')->loadUnchanged($host->id());
    $this->assertFalse($fresh_host->work->checklist->process());
    $this->assertSame($history, $this->container->get('checklist.attempt_journal')->history($attempt->id));
    $this->assertSame('failed-run', $this->reload($item)->get('state')->get('run_id')->getValue());
    $this->assertCount(1, Iteration::$calls);
    $this->container->get('state')->set('checklist_resolver_test.denied_field_operations', ['work' => ['edit']]);
    $this->expectException(AccessDeniedHttpException::class);
    $submitter->submit($item, TRUE);
  }

  /**
   * Account/host/field denial prevents submission and restores the caller.
   *
   * @dataProvider deniedSubmissions
   */
  public function testDeniedSubmission(string $denial): void {
    [$host, $item] = $this->work(record_attempt: FALSE);
    $current = $this->container->get('current_user');
    $current->setAccount($host);
    if ($denial === 'anonymous') {
      $current->setAccount(new UserSession());
    }
    elseif ($denial === 'blocked') {
      // Keep the stale active instance in the proxy to prove a fresh load.
      $this->container->get('entity_type.manager')->getStorage('user')->loadUnchanged($host->id())->block()->save();
    }
    elseif ($denial === 'host') {
      $other = User::create(['name' => 'Other', 'status' => 1]);
      $other->save();
      $current->setAccount($other);
    }
    else {
      $this->container->get('state')->set('checklist_resolver_test.denied_field_operations', ['work' => ['edit']]);
    }
    $uid = $current->id();
    try {
      $this->container->get('checklist.item_executor')->submit($item, TRUE);
      $this->fail('Unauthorized work must not be submitted.');
    }
    catch (AccessDeniedHttpException) {
      $this->assertSame($uid, $current->id());
      $this->assertNull($this->container->get('checklist.attempt_journal')->latest($item));
      $this->assertSame([], Iteration::$calls);
    }
  }

  /**
   * Supplies unavailable accounts and missing host/field access.
   */
  public static function deniedSubmissions(): array {
    return [['anonymous'], ['blocked'], ['host'], ['field']];
  }

  /**
   * Submission inside a transaction rolls back without orphan delivery.
   */
  public function testSubmissionRollback(): void {
    [, $item] = $this->work(record_attempt: FALSE);
    $journal = $this->container->get('checklist.attempt_journal');
    $transaction = $this->container->get('database')->startTransaction();
    $attempt = $this->container->get('checklist.item_executor')->submit($item);
    $this->assertSame([], Iteration::$calls);
    $this->assertEquals($attempt, $journal->latest($item));
    $transaction->rollBack();
    unset($transaction);
    $this->assertNull($journal->latest($item));
    $this->assertNull($journal->load($attempt->id));
    $this->assertSame(0, $this->container->get('checklist.item_iteration_scheduler')->dispatch());
    $new = $this->container->get('checklist.item_executor')->submit($item, TRUE);
    $this->assertNotSame($attempt->id, $new->id);
    $this->assertSame(1, $this->container->get('checklist.item_iteration_scheduler')->dispatch());
  }

  /**
   * A competing first submission keeps its original identity and executor.
   */
  public function testCompetingSubmission(): void {
    [$host, $item] = $this->work(record_attempt: FALSE);
    $journal = $this->container->get('checklist.attempt_journal');
    $competing = $this->getMockBuilder(ChecklistAttemptJournal::class)
      ->disableOriginalConstructor()->onlyMethods(['latest', 'create'])->getMock();
    $competing->method('latest')->willReturnCallback(fn($target) => $journal->latest($target));
    $competing->expects($this->once())->method('create')->willReturnCallback(function ($target) use ($journal, $host) {
      $journal->create($target, (int) $host->id(), (int) $host->id(), ChecklistAttempt::ACTION);
      throw new ChecklistAttemptConflictException('Competing submission won.');
    });
    $this->container->set('checklist.attempt_journal', $competing);
    $attempt = $this->container->get('checklist.item_executor')->submit($item, TRUE);
    $this->assertEquals($journal->latest($item), $attempt);
    $this->assertSame((int) $host->id(), $attempt->executor);
    $this->assertCount(1, $journal->history($attempt->id));
    $this->assertSame('1', (string) $this->container->get('current_user')->id());
  }

  /**
   * Unsaved working copies cannot become speculative background work.
   */
  public function testUnsavedItem(): void {
    [$host] = $this->work(record_attempt: FALSE);
    $item = ChecklistItem::create([
      'checklist_type' => 'context_test',
      'name' => 'new',
      'handler' => [
        'id' => 'iteration_test',
        'configuration' => ['context_mapping' => ['value' => 'checklist:entity.name.value']],
      ],
      'checklist' => ['entity' => $host, 'checklist_key' => 'work'],
    ]);
    $host->work->checklist->setItem('new', $item);
    $this->assertFalse($host->work->checklist->process());
    $this->assertTrue($item->isNew());
    $this->assertNull($this->container->get('checklist.attempt_journal')->latest($item));
    $this->expectException(\DomainException::class);
    $this->container->get('checklist.item_executor')->submit($item, TRUE);
  }

  /**
   * A later submission can consume a newly published sibling outcome.
   */
  public function testOutcomeEnablesSubmission(): void {
    [$host, $item] = $this->work(['context_mapping' => ['value' => 'item:source:result']], record_attempt: FALSE);
    $source = ChecklistItem::create([
      'checklist_type' => 'context_test',
      'name' => 'source',
      'title' => 'Source',
      'handler' => ['id' => 'state_test', 'configuration' => []],
      'checklist' => ['entity' => $host, 'checklist_key' => 'work'],
    ]);
    $source->save();
    $submitter = $this->container->get('checklist.item_executor');
    $this->assertNull($submitter->submit($item, TRUE));
    $source->setOutcome('result', 'Published')->setComplete()->save();
    $attempt = $submitter->submit($item, TRUE);
    $this->assertSame(ChecklistAttempt::QUEUED, $attempt->status);
    $this->container->get('checklist.item_executor')->run($attempt);
    $this->assertSame('Published', Iteration::$calls[0][3]);
  }

  /**
   * In-memory edits cannot bypass persisted actionability restrictions.
   */
  public function testStoredConfiguration(): void {
    [, $item] = $this->work(['conditions' => ['actionability' => ['id' => 'condition_constant:false']]], record_attempt: FALSE);
    $item->set('handler', [
      'id' => 'iteration_test',
      'configuration' => ['context_mapping' => ['value' => 'checklist:entity.name.value']],
    ]);
    $this->assertNull($this->container->get('checklist.item_executor')->submit($item, TRUE));
    $this->assertNull($this->container->get('checklist.attempt_journal')->latest($item));
  }

}
