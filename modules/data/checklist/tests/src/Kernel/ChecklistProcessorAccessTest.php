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
  public function denialProvider(): array {
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
