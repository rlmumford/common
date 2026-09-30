<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Form\ChecklistItemRetryForm;
use Drupal\Core\Form\FormState;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tests pinned, authorized retry confirmations and their row controls.
 *
 * @group checklist
 */
class ChecklistItemRetryFormTest extends ChecklistItemExecutionTestBase {

  /**
   * Creates failed saved automatic work with retained state.
   */
  protected function failure(): array {
    [$host, $item, $attempt] = $this->work(['fail' => TRUE]);
    $failed = $this->container->get('checklist.item_executor')->run($attempt);
    return [$host, $this->reload($item), $failed];
  }

  /**
   * Opening is read-only; submission queues a separately journaled attempt.
   *
   * @dataProvider modes
   */
  public function testConfirmation(string $mode): void {
    [$host, $item, $failed] = $this->failure();
    $journal = $this->container->get('checklist.attempt_journal');
    $form_object = ChecklistItemRetryForm::create($this->container);
    $state = new FormState();
    $form = $form_object->buildForm([], $state, $item->uuid(), $failed->id);
    $this->assertSame(ChecklistAttempt::RESUME, $form['mode']['#default_value']);
    $this->assertEquals($failed, $journal->latest($item));
    $this->assertTrue($this->reload($item)->isFailed());
    $state->setValue('mode', $mode);
    $form_object->submitForm($form, $state);
    $next = $journal->latest($item);
    $this->assertTrue($state->get('retry_queued'));
    $this->assertSame(ChecklistAttempt::QUEUED, $next->status);
    $this->assertSame($failed->id, $next->previous);
    $this->assertSame($mode, $next->mode);
    $saved = $this->reload($item);
    $this->assertSame($mode === ChecklistAttempt::RESUME ? 'failed-run' : NULL, $saved->get('state')->get('run_id')->getValue());
    $commands = $form_object->ajaxSubmit($form, $state)->getCommands();
    $this->assertSame('closeDialog', $commands[0]['command']);
    $this->assertContains('checklistReconcileRows', array_column($commands, 'command'));
    $this->assertTrue($saved->isIncomplete());
    $row = $this->container->get('checklist.row_builder')->build($host->work->checklist, $saved);
    $this->assertArrayNotHasKey('retry', $row);
  }

  /**
   * Provides the two explicit state-handling choices.
   */
  public static function modes(): array {
    return [[ChecklistAttempt::RESUME], [ChecklistAttempt::FRESH]];
  }

  /**
   * A second browser's old form cannot restart the newer attempt.
   */
  public function testStaleConfirmation(): void {
    [, $item, $failed] = $this->failure();
    $form_object = ChecklistItemRetryForm::create($this->container);
    $state = new FormState();
    $form = $form_object->buildForm([], $state, $item->uuid(), $failed->id);
    $next = $this->container->get('checklist.item_executor')->retry($item, $failed, ChecklistAttempt::RESUME, TRUE);
    $state->setValue('mode', ChecklistAttempt::FRESH);
    $form_object->submitForm($form, $state);
    $this->assertFalse((bool) $state->get('retry_queued'));
    $this->assertEquals($next, $this->container->get('checklist.attempt_journal')->latest($item));
    $this->assertSame('failed-run', $this->reload($item)->get('state')->get('run_id')->getValue());
    $rebuilt = $form_object->buildForm([], $state, $item->uuid(), $failed->id);
    $this->assertArrayNotHasKey('actions', $rebuilt);
    $this->assertArrayHasKey('changed', $rebuilt);
  }

  /**
   * Uncached stale POSTs retain the AJAX trigger until the conflict rebuild.
   */
  public function testUncachedStalePost(): void {
    [, $item, $failed] = $this->failure();
    $next = $this->container->get('checklist.item_executor')->retry($item, $failed, ChecklistAttempt::RESUME, TRUE);
    $form_object = ChecklistItemRetryForm::create($this->container);
    $state = (new FormState())
      ->setUserInput(['form_id' => $form_object->getFormId()])
      ->setValue('mode', ChecklistAttempt::FRESH);
    $form = $form_object->buildForm([], $state, $item->uuid(), $failed->id);
    $this->assertSame('::ajaxSubmit', $form['actions']['submit']['#ajax']['callback']);
    $form_object->submitForm($form, $state);
    $this->assertEquals($next, $this->container->get('checklist.attempt_journal')->latest($item));
    $rebuilt = $form_object->buildForm([], $state, $item->uuid(), $failed->id);
    $this->assertArrayHasKey('changed', $rebuilt);
    $this->assertArrayNotHasKey('actions', $rebuilt);
    $this->assertSame('failed-run', $this->reload($item)->get('state')->get('run_id')->getValue());
  }

  /**
   * Field access applies both to links and direct confirmation requests.
   */
  public function testDenied(): void {
    [$host, $item, $failed] = $this->failure();
    $row = $this->container->get('checklist.row_builder')->build($host->work->checklist, $item);
    $this->assertSame($failed->id, $row['retry']['link']['#url']->getRouteParameters()['attempt_id']);
    $this->container->get('state')->set('checklist_resolver_test.denied_field_operations', ['work' => ['edit']]);
    $this->container->get('entity_type.manager')->getAccessControlHandler('checklist_item')->resetCache();
    $row = $this->container->get('checklist.row_builder')->build($host->work->checklist, $item);
    $this->assertArrayNotHasKey('retry', $row);
    $this->expectException(AccessDeniedHttpException::class);
    ChecklistItemRetryForm::create($this->container)->buildForm([], new FormState(), $item->uuid(), $failed->id);
  }

  /**
   * Permission revocation after opening is rechecked before mutation.
   */
  public function testRevokedAfterOpening(): void {
    [, $item, $failed] = $this->failure();
    $form_object = ChecklistItemRetryForm::create($this->container);
    $state = (new FormState())->setValue('mode', ChecklistAttempt::FRESH);
    $form = $form_object->buildForm([], $state, $item->uuid(), $failed->id);
    $this->container->get('state')->set('checklist_resolver_test.denied_field_operations', ['work' => ['edit']]);
    try {
      $form_object->submitForm($form, $state);
      $this->fail('Changed permissions must prevent submission.');
    }
    catch (AccessDeniedHttpException) {
      $this->assertEquals($failed, $this->container->get('checklist.attempt_journal')->latest($item));
      $this->assertTrue($this->reload($item)->isFailed());
      $this->assertSame('failed-run', $this->reload($item)->get('state')->get('run_id')->getValue());
    }
  }

  /**
   * A missing or removed item produces a not-found response.
   */
  public function testMissingItem(): void {
    $this->expectException(NotFoundHttpException::class);
    ChecklistItemRetryForm::create($this->container)->buildForm([], new FormState(), 'missing', 'missing');
  }

}
