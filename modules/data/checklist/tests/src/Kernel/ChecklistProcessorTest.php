<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Entity\ChecklistItem;
use Drupal\checklist\Execution\ChecklistItemIterationScheduler;
use Drupal\checklist\Plugin\ChecklistItemHandler\StatefulChecklistItemHandlerInterface;
use Drupal\checklist_state_test\Plugin\ChecklistItemHandler\SingleStep;

/**
 * Tests checklist-wide inline processing and selective background handoff.
 *
 * @group checklist
 */
class ChecklistProcessorTest extends ChecklistItemExecutionTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    SingleStep::$calls = [];
    SingleStep::$during = NULL;
  }

  /**
   * Creates a single-step item with an optional downstream context mapping.
   */
  protected function singleStep(string $value = 'checklist:entity.name.value', string $handler = 'single_step_test'): array {
    [$host, $item] = $this->work(record_attempt: FALSE);
    $item->set('handler', ['id' => $handler, 'configuration' => ['context_mapping' => ['value' => $value]]])->save();
    return [$host, $item];
  }

  /**
   * A simple checklist finishes inline with an audit but no queue reservation.
   */
  public function testInlineCompletion(): void {
    [$host, $item] = $this->singleStep();
    $this->assertNotInstanceOf(StatefulChecklistItemHandlerInterface::class, $this->reload($item)->getHandler());
    $this->assertTrue($host->work->checklist->process());
    $this->assertSame(['worker'], SingleStep::$calls);
    $this->assertTrue($host->work->checklist->getItem('worker')->isComplete());
    $this->assertSame('Target-done', $this->reload($item)->get('outcomes')->get('result')->getValue());
    $journal = $this->container->get('checklist.attempt_journal');
    $attempt = $journal->latest($item);
    $this->assertSame(ChecklistAttempt::SUCCEEDED, $attempt->status);
    $this->assertSame(['queued', 'running', 'succeeded'], array_column($journal->history($attempt->id), 'to_status'));
    $reservation = $this->container->get('database')->select('checklist_attempt', 'a')->fields('a', ['dispatch_expires'])->condition('id', $attempt->id)->execute()->fetchField();
    $this->assertSame(0, (int) $reservation);
    $this->assertSame(0, $this->container->get('checklist.item_iteration_scheduler')->dispatch());
    $this->assertSame(0, $this->container->get('queue')->get(ChecklistItemIterationScheduler::QUEUE)->numberOfItems());
    $this->assertTrue($host->work->checklist->process());
    $this->assertSame(['worker'], SingleStep::$calls);
  }

  /**
   * Adds another ready item to the same authoritative checklist graph.
   */
  protected function addSource($host, string $name = 'source') {
    $source = ChecklistItem::create([
      'checklist_type' => 'context_test',
      'name' => $name,
      'title' => $name,
      'handler' => [
        'id' => 'single_step_test',
        'configuration' => ['context_mapping' => ['value' => 'checklist:entity.name.value']],
      ],
      'checklist' => ['entity' => $host, 'checklist_key' => 'work'],
    ]);
    $source->save();
    $host->work->checklist->setItem($name, $source);
    return $source;
  }

  /**
   * Reverse-ordered dependencies complete within the same request.
   */
  public function testOutcomeChain(): void {
    [$host, $item] = $this->singleStep('item:source:result');
    $source = $this->addSource($host);
    $this->assertSame(['worker', 'source'], array_keys($host->work->checklist->getOrderedItems()));
    $this->assertTrue($host->work->checklist->process());
    $this->assertSame(['source', 'worker'], SingleStep::$calls);
    $this->assertSame('Target-done-done', $this->reload($item)->get('outcomes')->get('result')->getValue());
    $this->assertTrue($this->reload($source)->isComplete());
    $this->assertSame(0, $this->container->get('queue')->get(ChecklistItemIterationScheduler::QUEUE)->numberOfItems());
  }

  /**
   * Explicit background handlers use the same executor and result contract.
   */
  public function testBackgroundExecution(): void {
    [$host, $item] = $this->singleStep(handler: 'background_step_test');
    $this->assertFalse($host->work->checklist->process());
    $this->assertSame([], SingleStep::$calls);
    $journal = $this->container->get('checklist.attempt_journal');
    $this->assertSame(ChecklistAttempt::QUEUED, $journal->latest($item)->status);
    checklist_cron();
    $queue = $this->container->get('queue')->get(ChecklistItemIterationScheduler::QUEUE);
    $message = $queue->claimItem();
    $worker = $this->container->get('plugin.manager.queue_worker')->createInstance(ChecklistItemIterationScheduler::QUEUE);
    $worker->processItem($message->data);
    $queue->deleteItem($message);
    $this->assertSame(['worker'], SingleStep::$calls);
    $this->assertSame(ChecklistAttempt::SUCCEEDED, $journal->latest($item)->status);
  }

  /**
   * Exhausting the budget defers remaining ready items, without running them.
   */
  public function testBudgetDeferral(): void {
    [$host, $item] = $this->singleStep();
    $source = $this->addSource($host);
    SingleStep::$during = function (): void {
      $this->now += 11;
    };
    $this->assertFalse($host->work->checklist->process());
    $this->assertSame(['worker'], SingleStep::$calls);
    $journal = $this->container->get('checklist.attempt_journal');
    $this->assertSame(ChecklistAttempt::SUCCEEDED, $journal->latest($item)->status);
    $this->assertSame(ChecklistAttempt::QUEUED, $journal->latest($source)->status);
    $this->assertSame(1, $this->container->get('checklist.item_iteration_scheduler')->dispatch());
  }

  /**
   * A zero inline budget still records ready work for later execution.
   */
  public function testZeroBudget(): void {
    [$host, $item] = $this->singleStep();
    $this->assertFalse($this->container->get('checklist.processor')->process($host->work->checklist, 0));
    $this->assertSame([], SingleStep::$calls);
    $this->assertSame(ChecklistAttempt::QUEUED, $this->container->get('checklist.attempt_journal')->latest($item)->status);
  }

  /**
   * Inline failures use the same audit and never cause implicit retries.
   */
  public function testInlineFailure(): void {
    [$host, $item] = $this->singleStep();
    SingleStep::$during = function (): void {
      throw new \RuntimeException('Private provider error');
    };
    try {
      $host->work->checklist->process();
      $this->fail('The handler exception must propagate after recording failure.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('Private provider error', $exception->getMessage());
    }
    $journal = $this->container->get('checklist.attempt_journal');
    $attempt = $journal->latest($item);
    $this->assertSame(ChecklistAttempt::FAILED, $attempt->status);
    $this->assertSame('Automatic iteration failed.', $journal->history($attempt->id)[2]['reason']);
    $this->assertFalse($host->work->checklist->process());
    $this->assertSame(['worker'], SingleStep::$calls);
    $this->assertSame('1', (string) $this->container->get('current_user')->id());
  }

}
