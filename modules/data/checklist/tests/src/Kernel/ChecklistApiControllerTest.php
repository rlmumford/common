<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist_api\Controller\ChecklistApiController;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
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
    'entity_test', 'checklist', 'checklist_api', 'checklist_context_test', 'checklist_resolver_test', 'plugin_reference',
    'typed_data', 'typed_data_plus', 'typed_data_reference',
    'typed_data_context_assignment', 'inline_entity_form',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('checklist_item');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['system', 'user']);
    foreach (['user', 'entity_test'] as $entity_type) {
      FieldStorageConfig::create([
        'field_name' => 'work',
        'entity_type' => $entity_type,
        'type' => 'checklist',
        'cardinality' => 1,
      ])->save();
      FieldConfig::create([
        'field_name' => 'work',
        'entity_type' => $entity_type,
        'bundle' => $entity_type,
      ])->save();
    }
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

    $state_response = $controller->itemState('entity_test', $host->id(), 'work:0', 'operation');
    $this->assertSame(200, $state_response->getStatusCode());
    $this->assertSame('operation', $state_response->getData(TRUE)['name']);
    $this->assertTrue($state_response->getData(TRUE)['actionable']);

    $operations_response = $controller->operations('entity_test', $host->id(), 'work:0', 'operation');
    $operations = $operations_response->getData(TRUE);
    $this->assertArrayHasKey('read', $operations['operations']);
    $this->assertSame('Produced', $operations['operations']['read']['label']);

    $request = Request::create('/', 'POST', [], [], [], [], json_encode([
      'operation' => 'read',
      'parameters' => [],
    ]));
    $result = $controller->execute($request, 'entity_test', $host->id(), 'work:0', 'operation');
    $this->assertSame(200, $result->getStatusCode());
    $this->assertSame(['value' => 'Produced', 'optional' => NULL], $result->getData(TRUE));
  }

  /**
   * Invalid input returns a client error without executing an operation.
   */
  public function testMalformedOperationRequest(): void {
    $host = $this->createHost();
    $controller = ChecklistApiController::create($this->container);
    $request = Request::create('/', 'POST', [], [], [], [], '{');

    $response = $controller->execute($request, 'entity_test', $host->id(), 'work', 'operation');

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
    $controller->operations('entity_test', $host->id(), 'work', 'operation');
  }

  /**
   * API routes are registered with their intended HTTP methods.
   */
  public function testApiRoutes(): void {
    $this->container->get('router.builder')->rebuild();
    $route_provider = $this->container->get('router.route_provider');
    $state_route = $route_provider->getRouteByName('checklist.item.state');
    $operation_route = $route_provider->getRouteByName('checklist.item.operation');

    $this->assertSame(['GET', 'HEAD'], $state_route->getMethods());
    $this->assertSame(['POST'], $operation_route->getMethods());
  }

  /**
   * Creates a saved host with an operation-consuming checklist item.
   */
  protected function createHost(): EntityTest {
    $host = EntityTest::create([
      'name' => 'Host',
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
    return $host;
  }

}
