<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist_api\Controller\ChecklistApiController;
use Drupal\checklist_state_test\Plugin\ChecklistItemHandler\Iteration;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests retry admission through the optional API and the shared executor.
 *
 * @group checklist
 */
class ChecklistRetryApiTest extends ChecklistItemExecutionTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['checklist_api'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('checklist', ['checklist_workspace']);
  }

  /**
   * Creates a failed item and acquires an API workspace for its current caller.
   */
  protected function failure(): array {
    [$host, $item, $attempt] = $this->work(['fail' => TRUE]);
    $item->setOutcome('result', 'Existing outcome')->save();
    $failed = $this->container->get('checklist.item_executor')->run($attempt);
    Iteration::$calls = [];
    $controller = ChecklistApiController::create($this->container);
    $response = $controller->acquireWorkspace($this->request([
      'instance_uuid' => $host->work->get(0)->getPersistedInstanceUuid(),
    ]), 'user', $host->id(), 'work');
    $this->assertSame(200, $response->getStatusCode());
    $lease = json_decode($response->getContent(), TRUE);
    $payload = [
      'instance_uuid' => $lease['instance_uuid'],
      'generation' => $lease['generation'],
      'expected_version' => $lease['version'],
      'attempt_id' => $failed->id,
      'attempt_version' => $failed->version,
      'mode' => ChecklistAttempt::RESUME,
    ];
    return [$host, $this->reload($item), $failed, $controller, $payload];
  }

  /**
   * Builds a JSON request without a browser form.
   */
  protected function request(array $payload): Request {
    return Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
  }

  /**
   * Both modes queue audited successors, and replay cannot reset newer work.
   *
   * @dataProvider modes
   */
  public function testRetry(string $mode): void {
    [$host, $item, $failed, $controller, $payload] = $this->failure();
    $payload['mode'] = $mode;
    $journal = $this->container->get('checklist.attempt_journal');
    $history = $journal->history($failed->id);
    $response = $controller->retry($this->request($payload), 'user', $host->id(), 'work:0', 'worker');
    $this->assertSame(202, $response->getStatusCode());
    $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    $data = json_decode($response->getContent(), TRUE);
    $next = $journal->latest($item);
    $this->assertSame($next->id, $data['attempt']['id']);
    $this->assertSame($failed->id, $data['attempt']['previous']);
    $this->assertSame($mode, $data['attempt']['mode']);
    $this->assertSame(ChecklistAttempt::QUEUED, $data['attempt']['status']);
    $this->assertSame($payload['expected_version'] + 1, $data['workspace']['version']);
    $this->assertSame(1, $next->initiator);
    $this->assertSame(1, $next->executor);
    $this->assertSame([], Iteration::$calls);
    $saved = $this->reload($item);
    $this->assertSame($mode === ChecklistAttempt::RESUME ? 'failed-run' : NULL, $saved->get('state')->get('run_id')->getValue());
    $this->assertSame('Existing outcome', $saved->get('outcomes')->get('result')->getValue());
    $this->assertSame($history, $journal->history($failed->id));
    $this->assertStringNotContainsString('failed-run', $response->getContent());
    $this->assertArrayNotHasKey('claim_token', $data['attempt']);
    $replayed = $controller->retry($this->request($payload), 'user', $host->id(), 'work', 'worker');
    $this->assertSame(409, $replayed->getStatusCode());
    $this->assertEquals($next, $journal->latest($item));
    $this->assertEquals($saved->toArray(), $this->reload($item)->toArray());
    // Even with current fences, queued work is not eligible for another retry.
    $payload['expected_version'] = $data['workspace']['version'];
    $payload['attempt_id'] = $next->id;
    $payload['attempt_version'] = $next->version;
    $active = $controller->retry($this->request($payload), 'user', $host->id(), 'work', 'worker');
    $this->assertSame(409, $active->getStatusCode());
    $this->assertEquals($next, $journal->latest($item));
  }

  /**
   * Provides explicit retry modes.
   */
  public static function modes(): array {
    return [[ChecklistAttempt::RESUME], [ChecklistAttempt::FRESH]];
  }

  /**
   * Malformed requests and stale fencing values do not change the item.
   */
  public function testInvalidRequests(): void {
    [$host, $item, $failed, $controller, $payload] = $this->failure();
    foreach ([
      ['mode' => 'automatic'],
      ['mode' => NULL],
      ['attempt_id' => []],
      ['attempt_version' => '3'],
      ['attempt_version' => 0],
      ['expected_version' => -1],
      ['generation' => '1'],
    ] as $changes) {
      $response = $controller->retry($this->request(array_replace($payload, $changes)), 'user', $host->id(), 'work', 'worker');
      $this->assertSame(400, $response->getStatusCode());
    }
    foreach ([
      ['instance_uuid' => 'replaced-instance'],
      ['generation' => $payload['generation'] + 1],
      ['expected_version' => $payload['expected_version'] + 1],
      ['attempt_id' => 'another-attempt'],
      ['attempt_version' => $payload['attempt_version'] + 1],
    ] as $changes) {
      $response = $controller->retry($this->request(array_replace($payload, $changes)), 'user', $host->id(), 'work', 'worker');
      $this->assertSame(409, $response->getStatusCode());
    }
    $this->assertEquals($failed, $this->container->get('checklist.attempt_journal')->latest($item));
    $this->assertSame('failed-run', $this->reload($item)->get('state')->get('run_id')->getValue());
    $status = $controller->workspaceStatus('user', $host->id(), 'work');
    $this->assertSame($payload['expected_version'], json_decode($status->getContent(), TRUE)['workspace']['version']);
    $this->assertSame([], Iteration::$calls);
  }

  /**
   * Readiness rejection retains failure and consumes the admitted API version.
   */
  public function testNotReady(): void {
    [$host, $item, $failed, $controller, $payload] = $this->failure();
    $configuration = $item->getHandler()->getConfiguration();
    $configuration['conditions']['actionability'] = ['id' => 'condition_constant:false'];
    $item->getHandler()->setConfiguration($configuration);
    $item->save();
    $response = $controller->retry($this->request($payload), 'user', $host->id(), 'work', 'worker');
    $this->assertSame(409, $response->getStatusCode());
    $this->assertEquals($failed, $this->container->get('checklist.attempt_journal')->latest($item));
    $this->assertTrue($this->reload($item)->isFailed());
    $status = $controller->workspaceStatus('user', $host->id(), 'work');
    $this->assertSame($payload['expected_version'] + 1, json_decode($status->getContent(), TRUE)['workspace']['version']);
  }

  /**
   * Expired workspaces and other users cannot use a captured retry packet.
   */
  public function testWorkspaceOwnership(): void {
    [$host, $item, $failed, $controller, $payload] = $this->failure();
    $this->container->get('current_user')->setAccount($host);
    $response = $controller->retry($this->request($payload), 'user', $host->id(), 'work', 'worker');
    $this->assertSame(409, $response->getStatusCode());
    $this->container->get('current_user')->setAccount($this->container->get('entity_type.manager')->getStorage('user')->load(1));
    $this->now += 301;
    $response = $controller->retry($this->request($payload), 'user', $host->id(), 'work', 'worker');
    $this->assertSame(409, $response->getStatusCode());
    $this->assertEquals($failed, $this->container->get('checklist.attempt_journal')->latest($item));
  }

  /**
   * Revoked item execution access is checked before changing the lease version.
   */
  public function testDeniedAccess(): void {
    [$host, $item, $failed, $controller, $payload] = $this->failure();
    $this->container->get('state')->set('checklist_resolver_test.denied_iteration_items', ['worker']);
    try {
      $controller->retry($this->request($payload), 'user', $host->id(), 'work', 'worker');
      $this->fail('Execution access must be checked.');
    }
    catch (AccessDeniedHttpException) {
      $this->assertEquals($failed, $this->container->get('checklist.attempt_journal')->latest($item));
      $status = $controller->workspaceStatus('user', $host->id(), 'work');
      $this->assertSame($payload['expected_version'], json_decode($status->getContent(), TRUE)['workspace']['version']);
    }
  }

}
