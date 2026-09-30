<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist\Execution\ChecklistItemNotReadyException;
use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\Core\Form\FormState;
use Drupal\checklist\Form\ChecklistItemActionForm;
use Drupal\checklist\Controller\ChecklistController;
use Drupal\checklist\Controller\ChecklistRefreshController;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Drupal\checklist_api\Controller\ChecklistApiController;
use Drupal\checklist\Workspace\ChecklistWorkspaceAddress;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\user\Entity\User;

/**
 * Tests automatic input requests through forms and API operations.
 *
 * @group checklist
 */
class ChecklistInputProgressTest extends ChecklistItemExecutionTestBase {

  /**
   * Runs an example item until its first iteration requests input.
   */
  protected function inputWork(): array {
    $this->enableModules(['checklist_reader_test', 'checklist_api']);
    $this->installSchema('checklist', ['checklist_workspace']);
    $host = User::create([
      'name' => 'Document review',
      'status' => 1,
      'work' => [
        'id' => 'context_test',
        'configuration' => [
          'default_items' => [
            'extract' => [
              'title' => 'Extract documents',
              'handler' => 'input_progress_example',
              'handler_configuration' => [],
            ],
          ],
        ],
      ],
    ]);
    $host->save();
    $item = $host->work->checklist->getItem('extract');
    $item->save();
    $attempt = $this->container->get('checklist.item_executor')->submit($item);
    return [$host, $this->reload($item), $attempt];
  }

  /**
   * Both input surfaces continue the original attempt and publish an outcome.
   *
   * @dataProvider inputPaths
   */
  public function testInputContinuation(string $path, ?string $mode = NULL): void {
    [$host, $item, $attempt] = $this->inputWork();
    $executor = $this->container->get('checklist.item_executor');
    if ($mode !== NULL) {
      $failed = $this->container->get('checklist.attempt_journal')->transition($attempt, ChecklistAttempt::FAILED, 1);
      $item->setFailed()->save();
      $attempt = $executor->retry($item, $failed, $mode);
      $item = $this->reload($item);
    }
    $this->assertSame(ChecklistAttempt::WAITING, $attempt->status);
    // A worker delivery must not re-run the provider or advance its journal.
    $this->assertSame($attempt->version, $executor->run($attempt)->version);
    $checklist = $this->container->get('checklist.resolver')->resolve($host, 'work');
    $checklist->setItem('extract', $item);
    $controller = ChecklistController::create($this->container);
    $this->assertTrue($controller->actionFormAccess($checklist, 'extract')->isAllowed());
    $row = $this->container->get('checklist.row_builder')->build($checklist, $item);
    $this->assertSame('true', $row['#attributes']['data-input-required']);
    $this->assertArrayHasKey('reference', $row['action_form']['input']);
    $dispatcher = $this->container->get('checklist.action_operation_dispatcher');
    $operations = $dispatcher->discover($checklist, 'extract');
    $this->assertSame($attempt->version, $operations['supply_reference']['parameters_schema']['properties']['version']['const']);
    $values = ['reference' => 'DOC-42', 'attempt_id' => $attempt->id, 'version' => $attempt->version];
    if ($path === 'operation') {
      $dispatcher->execute($checklist, 'extract', 'supply_reference', $values);
    }
    elseif ($path === 'api') {
      $address = ChecklistWorkspaceAddress::fromEntity($host, 'work', 0, 'work');
      $lease = $this->container->get('checklist.workspace_storage')->acquire($address, 1);
      $request = Request::create('/', 'POST', [], [], [], [], json_encode([
        'operation' => 'supply_reference',
        'instance_uuid' => $address->instanceUuid,
        'generation' => $lease->generation,
        'expected_version' => $lease->version,
        'parameters' => $values,
      ]));
      $api = ChecklistApiController::create($this->container);
      $response = $api->execute($request, 'user', $host->id(), 'work:0', 'extract');
      $this->assertSame(200, $response->getStatusCode());
    }
    else {
      $form = $this->container->get('plugin_form.factory')->createInstance($item->getHandler(), 'action');
      $state = (new FormState())->setValues($values);
      $build = $form->buildConfigurationForm([], $state);
      $wrapper = ChecklistItemActionForm::create($this->container);
      $wrapper->setChecklistItem($item);
      $wrapper->submitForm($build, $state);
      $this->assertFalse($wrapper->getChecklistItem()->getHandler()->getActionState()->inputRequired);
    }
    $queued = $this->container->get('checklist.attempt_journal')->latest($item);
    $this->assertSame($attempt->id, $queued->id);
    $this->assertSame($attempt->executor, $queued->executor);
    $this->assertSame(ChecklistAttempt::QUEUED, $queued->status);
    $fresh = $this->reload($item);
    $this->assertFalse($fresh->getHandler()->getActionState()->inputRequired);
    $this->assertSame('DOC-42', $fresh->get('state')->get('reference')->getValue());
    $checklist->setItem('extract', $fresh);
    $this->assertFalse($controller->actionFormAccess($checklist, 'extract')->isAllowed());
    $this->assertSame([], $dispatcher->discover($checklist, 'extract'));
    $row = $this->container->get('checklist.row_builder')->build($checklist, $fresh);
    $this->assertArrayNotHasKey('input', $row['action_form']);
    $before_completion = $this->container->get('datetime.time')->getCurrentTime();
    $done = $executor->run($queued);
    $this->assertSame(ChecklistAttempt::SUCCEEDED, $done->status);
    $fresh = $this->reload($item);
    $this->assertTrue($fresh->isComplete());
    $this->assertGreaterThanOrEqual($before_completion, (int) $fresh->get('completed')->value);
    $this->assertLessThanOrEqual($this->container->get('datetime.time')->getCurrentTime(), (int) $fresh->get('completed')->value);
    $this->assertTrue($fresh->get('state')->isEmpty());
    $this->assertSame('DOC-42', $fresh->get('outcomes')->get('reference')->getValue());
    $history = $this->container->get('checklist.attempt_journal')->history($attempt->id);
    $input_event = array_values(array_filter($history, static fn(array $event) => $event['reason'] === 'Requested input supplied.'));
    $this->assertCount(1, $input_event);
    $this->assertSame('1', (string) $input_event[0]['actor']);
  }

  /**
   * A queued retry must poll instead of opening a stale input request.
   */
  public function testQueuedRetryInput(): void {
    [$host, $item, $attempt] = $this->inputWork();
    $failed = $this->container->get('checklist.attempt_journal')->transition($attempt, ChecklistAttempt::FAILED, 1);
    $item->setFailed()->save();
    $executor = $this->container->get('checklist.item_executor');
    $next = $executor->retry($item, $failed, ChecklistAttempt::RESUME, TRUE);
    $row_builder = $this->container->get('checklist.row_builder');
    $row = $row_builder->build($host->work->checklist, $this->reload($item));
    $this->assertSame('true', $row['#attributes']['data-refresh-progress']);
    $this->assertSame('false', $row['#attributes']['data-input-required']);
    $this->assertArrayNotHasKey('input', $row['action_form']);
    $this->assertArrayNotHasKey('input_required', $row['progress']);
    $executor->run($next);
    $row = $row_builder->build($host->work->checklist, $this->reload($item));
    $this->assertSame('false', $row['#attributes']['data-refresh-progress']);
    $this->assertSame('true', $row['#attributes']['data-input-required']);
    $this->assertArrayHasKey('input', $row['action_form']);
  }

  /**
   * The same domain operation backs HTML and API submissions.
   */
  public static function inputPaths(): array {
    return [['form'], ['operation'], ['api'], ['form', ChecklistAttempt::RESUME], ['api', ChecklistAttempt::FRESH]];
  }

  /**
   * A concurrent claim prevents user input from overwriting worker state.
   */
  public function testClaimConflict(): void {
    [, $item, $attempt] = $this->inputWork();
    $this->container->get('checklist.attempt_claims')->claim($attempt);
    try {
      $item->getHandler()->supplyReference('DOC-42', $attempt->id, $attempt->version);
      $this->fail('Stale input must be rejected.');
    }
    catch (ChecklistAttemptConflictException) {
      $this->assertNull($this->reload($item)->get('state')->get('reference')->getValue());
    }
  }

  /**
   * Changed gates reject input even when a caller still has an old form.
   */
  public function testInputGate(): void {
    [, $item, $attempt] = $this->inputWork();
    $configuration = $item->getHandler()->getConfiguration();
    $configuration['conditions']['actionability'] = ['id' => 'condition_constant:false'];
    $item->getHandler()->setConfiguration($configuration);
    $item->save();
    $this->expectException(ChecklistItemNotReadyException::class);
    $item->getHandler()->supplyReference('DOC-42', $attempt->id, $attempt->version);
  }

  /**
   * Field edit access is required independently of the input-required flag.
   */
  public function testDeniedInput(): void {
    [$host, $item, $attempt] = $this->inputWork();
    $this->container->get('state')->set('checklist_resolver_test.denied_field_operations', ['work' => ['edit']]);
    $this->container->get('entity_type.manager')->getAccessControlHandler('checklist_item')->resetCache();
    $checklist = $this->container->get('checklist.resolver')->resolve($host, 'work');
    $checklist->setItem('extract', $item);
    $row = $this->container->get('checklist.row_builder')->build($checklist, $item);
    $this->assertArrayNotHasKey('input', $row['action_form']);
    $this->assertSame([], $this->container->get('checklist.action_operation_dispatcher')->discover($checklist, 'extract'));
    $this->expectException(AccessDeniedHttpException::class);
    $item->getHandler()->supplyReference('DOC-42', $attempt->id, $attempt->version);
  }

  /**
   * Invalid typed input rolls back both state and the journal transition.
   */
  public function testInvalidStateRollback(): void {
    [, $item, $attempt] = $this->inputWork();
    try {
      $this->container->get('checklist.item_executor')->acceptInput($item, $attempt, ['unknown' => 'value']);
      $this->fail('Undeclared state must be rejected.');
    }
    catch (\InvalidArgumentException) {
      $current = $this->container->get('checklist.attempt_journal')->latest($item);
      $this->assertSame($attempt->version, $current->version);
      $this->assertSame(ChecklistAttempt::WAITING, $current->status);
      $this->assertTrue($this->reload($item)->getHandler()->getActionState()->inputRequired);
    }
  }

  /**
   * A second submission cannot replace input accepted from another surface.
   */
  public function testDuplicateInput(): void {
    [, $item, $attempt] = $this->inputWork();
    $item->getHandler()->supplyReference('FIRST', $attempt->id, $attempt->version);
    try {
      $item->getHandler()->supplyReference('SECOND', $attempt->id, $attempt->version);
      $this->fail('Duplicate input must be rejected.');
    }
    catch (ChecklistAttemptConflictException) {
      $this->assertSame('FIRST', $this->reload($item)->get('state')->get('reference')->getValue());
    }
  }

  /**
   * Refresh reads fresh worker state without executing or writing an attempt.
   */
  public function testLiveRefresh(): void {
    [$host, $item, $attempt] = $this->inputWork();
    $controller = ChecklistRefreshController::create($this->container);
    $instance = $host->work->first()->getPersistedInstanceUuid();
    $checklist = $host->work->checklist;
    $this->container->get('checklist.tempstore_repository')->set($checklist);
    $before = $this->container->get('checklist.attempt_journal')->history($attempt->id);
    $response = $controller->refresh('user', $host->id(), 'work', $instance);
    $rows = array_values(array_filter($response->getCommands(), static fn(array $command) => $command['command'] === 'checklistReconcileRows'));
    $this->assertStringContainsString('Document reference', $rows[0]['data']);
    $this->assertStringContainsString('data-refresh-progress="false"', $rows[0]['data']);
    $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    $this->assertSame($before, $this->container->get('checklist.attempt_journal')->history($attempt->id));
    $item->getHandler()->supplyReference('DOC-42', $attempt->id, $attempt->version);
    $response = $controller->refresh('user', $host->id(), 'work', $instance);
    $rows = array_values(array_filter($response->getCommands(), static fn(array $command) => $command['command'] === 'checklistReconcileRows'));
    $this->assertStringContainsString('data-refresh-progress="true"', $rows[0]['data']);
    $this->assertStringNotContainsString('Document reference', $rows[0]['data']);
    $this->assertTrue($this->reload($item)->isIncomplete());
    $this->assertSame('DOC-42', $this->reload($item)->get('state')->get('reference')->getValue());
    $queued = $this->container->get('checklist.attempt_journal')->latest($item);
    $this->container->get('checklist.item_executor')->run($queued);
    $response = $controller->refresh('user', $host->id(), 'work', $instance);
    $rows = array_values(array_filter($response->getCommands(), static fn(array $command) => $command['command'] === 'checklistReconcileRows'));
    $this->assertStringContainsString('data-is-complete="true"', $rows[0]['data']);
    $this->assertStringContainsString('5 of 5', $rows[0]['data']);
    $this->assertStringContainsString('data-refresh-progress="false"', $rows[0]['data']);
    $this->container->get('router.builder')->rebuild();
    $routes = $this->container->get('router.route_provider');
    $this->assertSame(['GET'], $routes->getRouteByName('checklist.refresh')->getMethods());
    $this->assertStringEndsWith('::actionFormAccess', $routes->getRouteByName('checklist.item.action_form')->getRequirement('_custom_access'));
  }

  /**
   * A replaced checklist cannot receive a previous instance's refresh.
   */
  public function testRefreshInstanceMismatch(): void {
    [$host] = $this->inputWork();
    $this->expectException(ConflictHttpException::class);
    ChecklistRefreshController::create($this->container)->refresh('user', $host->id(), 'work', 'previous-instance');
  }

  /**
   * Refresh checks current field access before looking in the working copy.
   */
  public function testDeniedRefresh(): void {
    [$host] = $this->inputWork();
    $this->container->get('checklist.tempstore_repository')->set($host->work->checklist);
    $this->container->get('state')->set('checklist_resolver_test.denied_field_operations', ['work' => ['view']]);
    $this->expectException(AccessDeniedHttpException::class);
    ChecklistRefreshController::create($this->container)->refresh('user', $host->id(), 'work', $host->work->first()->getPersistedInstanceUuid());
  }

}
