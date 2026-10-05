<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist_api\Controller\ChecklistApiController;
use Drupal\checklist\Workspace\ChecklistWorkspaceAddress;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tests the optional JSON checklist API adapter.
 *
 * @group checklist
 */
class ChecklistApiControllerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'text', 'filter', 'options', 'entity',
    'checklist', 'checklist_api', 'checklist_context_test', 'checklist_resolver_test', 'plugin_reference',
    'typed_data', 'typed_data_plus', 'typed_data_reference',
    'typed_data_context_assignment', 'inline_entity_form',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('checklist_item');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('checklist', [
      'checklist_workspace', 'checklist_attempt',
      'checklist_attempt_head', 'checklist_attempt_event',
    ]);
    $this->installConfig(['system', 'user']);
    FieldStorageConfig::create([
      'field_name' => 'work',
      'entity_type' => 'user',
      'type' => 'checklist',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'work',
      'entity_type' => 'user',
      'bundle' => 'user',
    ])->save();
    $admin = User::create(['name' => 'Admin']);
    $admin->save();
    $this->container->get('current_user')->setAccount($admin);
  }

  /**
   * State, discovery and execution use the same saved host checklist.
   */
  public function testStateDiscoveryAndExecution(): void {
    $host = $this->createHost();
    $checklist = $host->get('work')->checklist;
    $source = $checklist->getItem('source');
    $source->setOutcome('value', 'Produced');
    $source->save();
    $controller = ChecklistApiController::create($this->container);

    $state_response = $controller->itemState('user', $host->id(), 'work:0', 'operation');
    $this->assertSame(200, $state_response->getStatusCode());
    $state = json_decode($state_response->getContent(), TRUE);
    $this->assertSame('operation', $state['name']);
    $this->assertTrue($state['actionable']);
    $this->assertNotEmpty($state['instance_uuid']);

    $operations_response = $controller->operations('user', $host->id(), 'work:0', 'operation');
    $operations = json_decode($operations_response->getContent(), TRUE);
    $this->assertArrayHasKey('read', $operations['operations']);
    $this->assertSame('Produced', $operations['operations']['read']['label']);
    $this->assertSame(
      'http://json-schema.org/draft-07/schema#',
      $operations['operations']['read']['parameters_schema']['$schema'],
    );
    $this->assertSame($state['instance_uuid'], $operations['instance_uuid']);

    $workspace_response = $this->acquireWorkspace($controller, $host, $state['instance_uuid']);
    $workspace = json_decode($workspace_response->getContent(), TRUE);
    $this->assertSame(200, $workspace_response->getStatusCode());
    $this->assertSame($state['instance_uuid'], $workspace['instance_uuid']);

    $request = Request::create('/', 'POST', [], [], [], [], json_encode([
      'operation' => 'read',
      'instance_uuid' => $workspace['instance_uuid'],
      'generation' => $workspace['generation'],
      'expected_version' => $workspace['version'],
      'parameters' => (object) [],
    ]));
    $result = $controller->execute($request, 'user', $host->id(), 'work:0', 'operation');
    $this->assertSame(200, $result->getStatusCode());
    $result_data = json_decode($result->getContent(), TRUE);
    $this->assertSame(['value' => 'Produced', 'optional' => NULL], $result_data['result']);
    $this->assertSame(1, $result_data['workspace']['version']);
  }

  /**
   * Schema-invalid operation input returns 400 before handler execution.
   */
  public function testInvalidOperationParameters(): void {
    $host = $this->createHost();
    $source = $host->get('work')->checklist->getItem('source');
    $source->setOutcome('value', 'Produced');
    $source->save();
    $controller = ChecklistApiController::create($this->container);
    $lease_response = $this->acquireWorkspace($controller, $host, $host->get('work')->first()->getPersistedInstanceUuid());
    $workspace = json_decode($lease_response->getContent(), TRUE);
    $request = Request::create('/', 'POST', [], [], [], [], json_encode([
      'operation' => 'read',
      'instance_uuid' => $workspace['instance_uuid'],
      'generation' => $workspace['generation'],
      'expected_version' => $workspace['version'],
      'parameters' => ['unexpected' => TRUE],
    ]));

    $response = $controller->execute($request, 'user', $host->id(), 'work:0', 'operation');

    $this->assertSame(400, $response->getStatusCode());
    $this->assertSame([], $this->container->get('state')->get('checklist_context_test.runs', []));
    $this->assertSame(0, $this->container->get('checklist.workspace_storage')->load(
      ChecklistWorkspaceAddress::fromEntity($host, 'work', 0, 'work'),
    )->version);
  }

  /**
   * Invalid input returns a client error without executing an operation.
   */
  public function testMalformedOperationRequest(): void {
    $host = $this->createHost();
    $controller = ChecklistApiController::create($this->container);
    $request = Request::create('/', 'POST', [], [], [], [], '{');

    $response = $controller->execute($request, 'user', $host->id(), 'work', 'operation');

    $this->assertSame(400, $response->getStatusCode());
    $this->assertSame([], $this->container->get('state')->get('checklist_context_test.runs', []));
  }

  /**
   * Host access is rechecked for API state reads and operations.
   */
  public function testDeniedChecklistFieldAccess(): void {
    $host = $this->createHost();
    $this->container->get('state')->set('checklist_resolver_test.denied_field_operations', ['work' => ['edit']]);
    $controller = ChecklistApiController::create($this->container);

    $this->expectException(AccessDeniedHttpException::class);
    $controller->operations('user', $host->id(), 'work', 'operation');
  }

  /**
   * API routes are registered with their intended HTTP methods.
   */
  public function testApiRoutes(): void {
    $this->container->get('router.builder')->rebuild();
    $route_provider = $this->container->get('router.route_provider');
    $state_route = $route_provider->getRouteByName('checklist.item.state');
    $operation_route = $route_provider->getRouteByName('checklist.item.operation');
    $retry_route = $route_provider->getRouteByName('checklist.item.retry_api');
    $this->assertSame(['POST'], $retry_route->getMethods());
    $this->assertSame('json', $retry_route->getRequirement('_format'));
    $this->assertSame('TRUE', $retry_route->getOption('no_cache'));
    $workspace_status_route = $route_provider->getRouteByName('checklist.workspace.status');
    $workspace_acquire_route = $route_provider->getRouteByName('checklist.workspace.acquire');
    $workspace_renew_route = $route_provider->getRouteByName('checklist.workspace.renew');
    $workspace_release_route = $route_provider->getRouteByName('checklist.workspace.release');

    $read_routes = [
      'checklist.item.state', 'checklist.item.operations',
      'checklist.item.history', 'checklist.workspace.status',
    ];
    foreach ($read_routes as $name) {
      $this->assertTrue($this->container->get('access_manager')->checkNamedRoute($name, [
        'entity_type' => 'user',
        'entity_id' => '1',
        'checklist' => 'work',
        'item_name' => 'operation',
      ]));
    }
    $this->assertSame(['GET', 'HEAD'], $state_route->getMethods());
    $this->assertSame(['GET', 'HEAD'], $workspace_status_route->getMethods());
    $this->assertSame(['POST'], $operation_route->getMethods());
    $this->assertSame(['POST'], $workspace_acquire_route->getMethods());
    $this->assertSame(['PATCH'], $workspace_renew_route->getMethods());
    $this->assertSame(['DELETE'], $workspace_release_route->getMethods());
    $write_routes = [
      $retry_route, $operation_route, $workspace_acquire_route,
      $workspace_renew_route, $workspace_release_route,
    ];
    foreach ($write_routes as $write_route) {
      $this->assertSame('TRUE', $write_route->getRequirement('_csrf_request_header_token'));
      $this->assertSame('TRUE', $write_route->getRequirement('_user_is_logged_in'));
    }
  }

  /**
   * Stale workspace versions cannot repeat an already accepted operation.
   */
  public function testStaleWorkspaceVersionCannotRepeatOperation(): void {
    $host = $this->createHost();
    $source = $host->get('work')->checklist->getItem('source');
    $source->setOutcome('value', 'Produced');
    $source->save();
    $controller = ChecklistApiController::create($this->container);
    $uuid = $host->get('work')->first()->getPersistedInstanceUuid();
    $workspace = json_decode($this->acquireWorkspace($controller, $host, $uuid)->getContent(), TRUE);
    $payload = [
      'operation' => 'read',
      'instance_uuid' => $workspace['instance_uuid'],
      'generation' => $workspace['generation'],
      'expected_version' => $workspace['version'],
      'parameters' => (object) [],
    ];
    $first = $controller->execute(
      Request::create('/', 'POST', [], [], [], [], json_encode($payload)),
      'user',
      $host->id(),
      'work:0',
      'operation',
    );
    $stale = $controller->execute(
      Request::create('/', 'POST', [], [], [], [], json_encode($payload)),
      'user',
      $host->id(),
      'work:0',
      'operation',
    );

    $this->assertSame(200, $first->getStatusCode());
    $this->assertSame(409, $stale->getStatusCode());
    $this->assertCount(1, $this->container->get('state')->get('checklist_context_test.runs', []));
  }

  /**
   * An old instance UUID cannot execute against a replacement field item.
   */
  public function testReplacedChecklistRejectsStaleInstanceUuid(): void {
    $host = $this->createHost();
    $source = $host->get('work')->checklist->getItem('source');
    $source->setOutcome('value', 'Produced');
    $source->save();
    $controller = ChecklistApiController::create($this->container);
    $old_uuid = $host->get('work')->get(0)->getPersistedInstanceUuid();
    $workspace = json_decode($this->acquireWorkspace($controller, $host, $old_uuid)->getContent(), TRUE);

    $host->set('work', [
      'id' => 'context_test',
      'configuration' => [
        'default_items' => [
          'replacement' => [
            'title' => 'Replacement',
            'handler' => 'decision',
            'handler_configuration' => ['question' => 'Replacement', 'options' => ['yes' => ['label' => 'Yes']]],
          ],
        ],
      ],
    ]);
    $host->save();
    $request = Request::create('/', 'POST', [], [], [], [], json_encode([
      'operation' => 'read',
      'instance_uuid' => $workspace['instance_uuid'],
      'generation' => $workspace['generation'],
      'expected_version' => $workspace['version'],
      'parameters' => (object) [],
    ]));

    $response = $controller->execute($request, 'user', $host->id(), 'work:0', 'operation');

    $this->assertSame(409, $response->getStatusCode());
    $this->assertSame([], $this->container->get('state')->get('checklist_context_test.runs', []));
  }

  /**
   * Workspace lease endpoints renew and release only the current generation.
   */
  public function testWorkspaceRenewAndRelease(): void {
    $host = $this->createHost();
    $controller = ChecklistApiController::create($this->container);
    $uuid = $host->get('work')->get(0)->getPersistedInstanceUuid();
    $workspace = json_decode($this->acquireWorkspace($controller, $host, $uuid)->getContent(), TRUE);
    $lease_request = Request::create('/', 'PATCH', [], [], [], [], json_encode([
      'instance_uuid' => $uuid,
      'generation' => $workspace['generation'],
    ]));

    $renewed = $controller->renewWorkspace($lease_request, 'user', $host->id(), 'work:0');
    $renewed_workspace = json_decode($renewed->getContent(), TRUE);
    $this->assertSame(200, $renewed->getStatusCode());
    $this->assertSame($workspace['generation'], $renewed_workspace['generation']);
    $this->assertGreaterThanOrEqual($workspace['expires'], $renewed_workspace['expires']);

    $release_request = Request::create('/', 'DELETE', [], [], [], [], json_encode([
      'instance_uuid' => $uuid,
      'generation' => $renewed_workspace['generation'],
    ]));
    $released = $controller->releaseWorkspace($release_request, 'user', $host->id(), 'work:0');
    $this->assertSame(204, $released->getStatusCode());
    $this->assertSame('', $released->getContent());
    $this->assertSame(409, $controller->renewWorkspace($lease_request, 'user', $host->id(), 'work:0')->getStatusCode());
  }

  /**
   * Workspace status reads do not acquire or renew a lease.
   */
  public function testWorkspaceStatusIsReadOnly(): void {
    $host = $this->createHost();
    $controller = ChecklistApiController::create($this->container);
    $uuid = $host->get('work')->get(0)->getPersistedInstanceUuid();
    $address = ChecklistWorkspaceAddress::fromEntity($host, 'work', 0, 'work');

    $available = json_decode($controller->workspaceStatus('user', $host->id(), 'work:0')->getContent(), TRUE)['workspace'];

    $this->assertSame($uuid, $available['instance_uuid']);
    $this->assertFalse($available['locked']);
    $this->assertFalse($available['owned_by_current_user']);
    $this->assertNull($available['generation']);
    $this->assertNull($this->container->get('checklist.workspace_storage')->load($address));

    $lease = json_decode($this->acquireWorkspace($controller, $host, $uuid)->getContent(), TRUE);
    $before = $this->container->get('checklist.workspace_storage')->load($address);
    $owned = json_decode($controller->workspaceStatus('user', $host->id(), 'work:0')->getContent(), TRUE)['workspace'];
    $after = $this->container->get('checklist.workspace_storage')->load($address);

    $this->assertTrue($owned['owned_by_current_user']);
    $this->assertFalse($owned['locked']);
    $this->assertSame($lease['generation'], $owned['generation']);
    $this->assertSame($lease['version'], $owned['version']);
    $this->assertArrayNotHasKey('owner', $owned);
    $this->assertSame($before->expires, $after->expires);
    $this->assertSame($before->generation, $after->generation);
    $this->assertSame($before->version, $after->version);

    $storage = $this->container->get('checklist.workspace_storage');
    $storage->release($after);
    $storage->acquire($address, 999);
    $locked = json_decode($controller->workspaceStatus('user', $host->id(), 'work:0')->getContent(), TRUE)['workspace'];
    $this->assertTrue($locked['locked']);
    $this->assertFalse($locked['owned_by_current_user']);
    $this->assertNull($locked['generation']);
    $this->assertNull($locked['version']);
    $this->assertNull($locked['expires']);
    $this->assertArrayNotHasKey('owner', $locked);
  }

  /**
   * Acquires the checklist workspace through the API controller.
   */
  protected function acquireWorkspace(ChecklistApiController $controller, User $host, ?string $instance_uuid): JsonResponse {
    $request = Request::create('/', 'POST', [], [], [], [], json_encode(['instance_uuid' => $instance_uuid]));
    return $controller->acquireWorkspace($request, 'user', $host->id(), 'work:0');
  }

  /**
   * Creates a saved host with an operation-consuming checklist item.
   */
  protected function createHost(): User {
    $host = User::create([
      'name' => 'Host',
      'status' => 1,
      'work' => [
        'id' => 'context_test',
        'configuration' => [
          'default_items' => [
            'source' => [
              'title' => 'Source',
              'handler' => 'context_producer',
              'handler_configuration' => [],
            ],
            'operation' => [
              'title' => 'Operation',
              'handler' => 'operation_consumer',
              'handler_configuration' => [
                'stay_incomplete' => TRUE,
                'context_mapping' => [
                  'value' => 'item:source:value',
                  'optional' => 'item:source:details.label',
                ],
              ],
            ],
          ],
        ],
      ],
    ]);
    $host->save();
    $this->container->get('current_user')->setAccount($host);
    return $host;
  }

  /**
   * Paging remains on the chosen attempt even after a successor is created.
   */
  public function testHistoryPaginationAndSuccessors(): void {
    $host = $this->createHost();
    $item = $host->get('work')->checklist->getItem('operation');
    $item->save();
    $journal = $this->container->get('checklist.attempt_journal');
    $controller = ChecklistApiController::create($this->container);
    $empty = json_decode($controller->history(Request::create('/'), 'user', $host->id(), 'work', 'operation')->getContent(), TRUE);
    $this->assertNull($empty['attempt']);
    $this->assertSame([], $empty['events']);

    $attempt = $journal->create($item, 12, 13, ChecklistAttempt::ACTION);
    $attempt = $journal->transition($attempt, ChecklistAttempt::RUNNING, 13);
    $attempt = $journal->transition($attempt, ChecklistAttempt::WAITING, 13);
    $attempt = $journal->transition($attempt, ChecklistAttempt::QUEUED, 14, 'Requested input supplied.');
    $attempt = $journal->transition($attempt, ChecklistAttempt::RUNNING, 13);
    $attempt = $journal->transition($attempt, ChecklistAttempt::FAILED, 13, 'Could not finish.');
    $response = $controller->history(Request::create('/', 'GET', ['limit' => '2']), 'user', $host->id(), 'work:0', 'operation');
    $page = json_decode($response->getContent(), TRUE);
    $this->assertSame($attempt->id, $page['attempt']['id']);
    $this->assertSame(12, $page['attempt']['initiator']);
    $this->assertSame(13, $page['attempt']['executor']);
    $this->assertSame([1, 2], array_column($page['events'], 'version'));
    $this->assertSame(2, $page['next_after_version']);
    $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    $this->assertSame($host->get('work')->first()->getPersistedInstanceUuid(), $page['instance_uuid']);
    $this->assertSame([
      'id', 'previous', 'mode', 'status', 'version', 'initiator', 'executor', 'authorization',
      'path', 'operation', 'created', 'changed',
    ], array_keys($page['attempt']));

    $successor = $journal->create($item, 15, 13, ChecklistAttempt::ACTION, NULL, ChecklistAttempt::RESUME, $attempt->id);
    $second = json_decode($controller->history(Request::create('/', 'GET', [
      'attempt' => $attempt->id,
      'after_version' => $page['next_after_version'],
      'limit' => 2,
    ]), 'user', $host->id(), 'work', 'operation')->getContent(), TRUE);
    $this->assertSame([3, 4], array_column($second['events'], 'version'));
    $this->assertSame(14, $second['events'][1]['actor']);
    $this->assertSame('Requested input supplied.', $second['events'][1]['reason']);
    $latest = $this->container->get('checklist.item_reader')->readHistory($host, 'work', 0, 'operation');
    $this->assertSame($successor->id, $latest['attempt']['id']);
    $this->assertSame($attempt->id, $latest['attempt']['previous']);
    $this->assertSame(1, $journal->latest($item)->version);
    $this->assertCount(6, $journal->history($attempt->id));
    $this->assertSame([], $this->container->get('state')->get('checklist_context_test.runs', []));
    $end = $this->container->get('checklist.item_reader')->readHistory($host, 'work', 0, 'operation', $attempt->id, 6);
    $this->assertSame([], $end['events']);
    $this->assertSame(6, $end['next_after_version']);
  }

  /**
   * An attempt ID cannot disclose a different item's history.
   */
  public function testHistoryRejectsAnotherItem(): void {
    $host = $this->createHost();
    $source = $host->get('work')->checklist->getItem('source');
    $source->save();
    $attempt = $this->container->get('checklist.attempt_journal')->create($source, 1, 1, ChecklistAttempt::ACTION);
    $this->expectException(NotFoundHttpException::class);
    $this->container->get('checklist.item_reader')->readHistory($host, 'work', 0, 'operation', $attempt->id);
  }

  /**
   * Field denial applies before attempting to read the journal.
   */
  public function testHistoryRejectsDeniedField(): void {
    $host = $this->createHost();
    $this->container->get('state')->set('checklist_resolver_test.denied_field_operations', ['work' => ['view']]);
    $this->expectException(AccessDeniedHttpException::class);
    $this->container->get('checklist.item_reader')->readHistory($host, 'work', 0, 'operation');
  }

  /**
   * Hidden items produce not-found responses before journal lookup.
   */
  public function testHistoryRejectsHiddenItem(): void {
    $this->enableModules(['checklist_reader_test']);
    $host = $this->createHost();
    $checklist = $host->get('work')->checklist;
    $item = $checklist->getItem('source');
    $item->set('name', 'hidden');
    $checklist->setItem('hidden', $item);
    $this->expectException(NotFoundHttpException::class);
    $this->container->get('checklist.item_reader')->readHistory($host, 'work', 0, 'hidden');
  }

  /**
   * Missing attempts return 404 rather than an empty successful history.
   */
  public function testHistoryRejectsUnknownAttempt(): void {
    $host = $this->createHost();
    $this->expectException(NotFoundHttpException::class);
    $this->container->get('checklist.item_reader')->readHistory($host, 'work', 0, 'operation', $this->container->get('uuid')->generate());
  }

  /**
   * History requires view access but does not require field edit access.
   */
  public function testHistoryAllowsReadOnlyField(): void {
    $host = $this->createHost();
    $this->container->get('state')->set('checklist_resolver_test.denied_field_operations', ['work' => ['edit']]);
    $history = $this->container->get('checklist.item_reader')->readHistory($host, 'work', 0, 'operation');
    $this->assertNull($history['attempt']);
    $this->assertSame([], $history['events']);
    $this->assertTrue($host->get('work')->checklist->getItem('operation')->isNew());
    $this->assertFalse($this->container->get('checklist.tempstore_repository')->has($host->get('work')->checklist));
  }

  /**
   * A different account cannot inspect a host it cannot view.
   */
  public function testHistoryRejectsDeniedHost(): void {
    $host = $this->createHost();
    $this->container->get('current_user')->setAccount(new AnonymousUserSession());
    $this->expectException(AccessDeniedHttpException::class);
    ChecklistApiController::create($this->container)->history(Request::create('/'), 'user', $host->id(), 'work', 'operation');
  }

  /**
   * Invalid pagination cannot produce unbounded queries or array input errors.
   */
  public function testInvalidHistoryQuery(): void {
    $host = $this->createHost();
    $controller = ChecklistApiController::create($this->container);
    foreach ([
      ['limit' => 0], ['limit' => 101], ['after_version' => -1],
      ['limit' => ['50']], ['attempt' => ['id']], ['after_version' => '1.5'],
    ] as $query) {
      $response = $controller->history(Request::create('/', 'GET', $query), 'user', $host->id(), 'work', 'operation');
      $this->assertSame(400, $response->getStatusCode());
    }
    $this->container->get('router.builder')->rebuild();
    $route = $this->container->get('router.route_provider')->getRouteByName('checklist.item.history');
    $this->assertSame(['GET', 'HEAD'], $route->getMethods());
    $this->assertSame('TRUE', $route->getOption('no_cache'));
  }

}
