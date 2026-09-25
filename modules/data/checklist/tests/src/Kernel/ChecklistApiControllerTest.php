<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist_api\Controller\ChecklistApiController;
use Drupal\checklist\Workspace\ChecklistWorkspaceAddress;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
    $this->installSchema('checklist', ['checklist_workspace']);
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
    $workspace_acquire_route = $route_provider->getRouteByName('checklist.workspace.acquire');
    $workspace_renew_route = $route_provider->getRouteByName('checklist.workspace.renew');
    $workspace_release_route = $route_provider->getRouteByName('checklist.workspace.release');

    $this->assertSame(['GET', 'HEAD'], $state_route->getMethods());
    $this->assertSame(['POST'], $operation_route->getMethods());
    $this->assertSame(['POST'], $workspace_acquire_route->getMethods());
    $this->assertSame(['PATCH'], $workspace_renew_route->getMethods());
    $this->assertSame(['DELETE'], $workspace_release_route->getMethods());
    foreach ([$operation_route, $workspace_acquire_route, $workspace_renew_route, $workspace_release_route] as $write_route) {
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

}
