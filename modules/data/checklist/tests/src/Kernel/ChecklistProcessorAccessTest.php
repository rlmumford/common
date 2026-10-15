<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests execution access on the legacy synchronous processing path.
 *
 * @group checklist
 */
class ChecklistProcessorAccessTest extends ChecklistItemExecutionTestBase {

  /**
   * Creates a legacy automatic item without recording an execution attempt.
   */
  protected function synchronousWork(): array {
    [$host, $item] = $this->work(record_attempt: FALSE);
    $item->set('handler', ['id' => 'context_producer', 'configuration' => []])->save();
    return [$host, $item];
  }

  /**
   * Opt-in automatic execution does not grant manual update or decision access.
   */
  public function testExecutionOnlyPolicy(): void {
    [$host, $item] = $this->synchronousWork();
    $entities = $this->container->get('entity_type.manager');
    $state = $this->container->get('state');
    $state->set('checklist_resolver_test.execution_only', TRUE);
    $entities->getAccessControlHandler('user')->resetCache();
    try {
      $host->work->checklist->process();
      $this->fail('Hosts without opt-in must retain update access.');
    }
    catch (AccessDeniedHttpException) {
      $this->assertTrue($this->reload($item)->isIncomplete());
    }
    $entities->getDefinition('user')->set('checklist_separate_execution_access', TRUE);
    $this->assertFalse($host->access('update'));
    $this->assertTrue($host->work->checklist->process());
    $this->assertSame('Produced', $this->reload($item)->get('outcomes')->get('value')->getValue());

    $manual = $item->createDuplicate();
    $manual->set('name', 'decision')->set('handler', ['id' => 'decision', 'configuration' => [
      'question' => 'Confirm', 'options' => ['yes' => ['label' => 'Yes']],
    ]])->set('status', \Drupal\checklist\Entity\ChecklistItemInterface::STATUS_INCOMPLETE)->save();
    $host->work->checklist->setItem('decision', $manual);
    $this->assertFalse($manual->access('execute action operation'));
    $this->assertFalse($manual->access('execute iteration'));
    $this->expectException(AccessDeniedHttpException::class);
    $this->container->get('checklist.action_operation_dispatcher')->execute($host->work->checklist, 'decision', 'choose', ['choice' => 'yes']);
  }

  /**
   * Iterative workers recheck the separate permission before a continuation.
   */
  public function testExecutionPermissionRevokedBetweenIterations(): void {
    [$host, $item, $attempt] = $this->work();
    $entities = $this->container->get('entity_type.manager');
    $entities->getDefinition('user')->set('checklist_separate_execution_access', TRUE);
    $this->container->get('state')->set('checklist_resolver_test.execution_only', TRUE);
    $entities->getAccessControlHandler('user')->resetCache();
    $runner = $this->container->get('checklist.item_executor');
    $waiting = $runner->run($attempt);
    $this->assertSame(\Drupal\checklist\Attempt\ChecklistAttempt::WAITING, $waiting->status);
    $this->now += 5;
    $this->container->get('state')->set('checklist_resolver_test.execution_only', 'revoked');
    try {
      $runner->run($waiting);
      $this->fail('Revoked execution must stop the next iteration.');
    }
    catch (AccessDeniedHttpException) {
      $this->assertTrue($this->reload($item)->isIncomplete());
      $this->assertCount(1, \Drupal\checklist_state_test\Plugin\ChecklistItemHandler\Iteration::$calls);
    }
  }

  /**
   * Denied hosts and fields cannot run handlers or finish the checklist.
   *
   * @dataProvider denialProvider
   */
  public function testDeniedHostOrField(string $denial): void {
    [$host, $item] = $this->synchronousWork();
    if ($denial === 'host') {
      $this->container->get('current_user')->setAccount(new AnonymousUserSession());
    }
    else {
      $this->container->get('state')->set('checklist_resolver_test.denied_field_operations', ['work' => [$denial]]);
    }
    try {
      $host->work->checklist->process();
      $this->fail('Processing must reject the inaccessible checklist.');
    }
    catch (AccessDeniedHttpException) {
      $fresh = $this->reload($item);
      $this->assertTrue($fresh->isIncomplete());
      $this->assertTrue($fresh->get('outcomes')->isEmpty());
      $this->assertTrue($fresh->get('completed')->isEmpty());
    }
  }

  /**
   * Supplies host, field-view and field-edit access denials.
   */
  public static function denialProvider(): array {
    return [['host'], ['view'], ['edit']];
  }

  /**
   * Item-specific denial prevents action without recording a handler failure.
   */
  public function testDeniedItemCanRunAfterAccessIsRestored(): void {
    [$host, $item] = $this->synchronousWork();
    $state = $this->container->get('state');
    $state->set('checklist_resolver_test.denied_iteration_items', ['worker']);
    $this->assertFalse($host->work->checklist->process());
    $this->assertTrue($this->reload($item)->isIncomplete());
    $this->assertTrue($this->reload($item)->get('outcomes')->isEmpty());
    $state->delete('checklist_resolver_test.denied_iteration_items');
    $this->container->get('entity_type.manager')->getAccessControlHandler('checklist_item')->resetCache();
    $this->assertTrue($host->work->checklist->process());
    $this->assertSame('Produced', $this->reload($item)->get('outcomes')->get('value')->getValue());
  }

  /**
   * Host access is checked even when every item is already complete.
   */
  public function testDeniedCompletion(): void {
    [$host, $item] = $this->synchronousWork();
    $item->setComplete()->save();
    $this->container->get('state')->set('checklist_resolver_test.denied_field_operations', ['work' => ['edit']]);
    $this->expectException(AccessDeniedHttpException::class);
    $host->work->checklist->process();
  }

  /**
   * Access changes during a pass stop later work and completion callbacks.
   */
  public function testAccessRevokedDuringProcessing(): void {
    [$host, $first] = $this->synchronousWork();
    $second = $first->createDuplicate();
    $second->set('name', 'second')->save();
    $host->work->checklist->setItem('second', $second);
    $this->container->get('state')->set('checklist_resolver_test.revoke_after_item', 'worker');
    try {
      $host->work->checklist->process();
      $this->fail('Revoked access must stop subsequent work.');
    }
    catch (AccessDeniedHttpException) {
      $this->assertTrue($this->reload($first)->isComplete());
      $this->assertTrue($this->reload($second)->isIncomplete());
      $this->assertTrue($this->reload($second)->get('outcomes')->isEmpty());
    }
  }

}
