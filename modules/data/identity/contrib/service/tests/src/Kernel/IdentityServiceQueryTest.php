<?php

namespace Drupal\Tests\identity_service\Kernel;

use Drupal\identity_service\Controller\ServiceController;
use Drupal\KernelTests\KernelTestBase;
use Drupal\rest\ResourceResponseInterface;
use Drupal\Tests\identity\Traits\IdentityCreationTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the identity service query end point.
 */
class IdentityServiceQueryTest extends KernelTestBase {
  use IdentityCreationTestTrait;
  use UserCreationTrait;

  /**
   * The modules required for this test to run.
   *
   * @var string[]
   */
  protected static $modules = ["identity_service", "identity", "basic_auth",
    "serialization", "rest", "entity", "name", "telephone", "datetime", "user",
    "system", "options", "text",
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);

    $this->setUpIdentityCreationTrait();
  }

  /**
   * Test querying by the id of identites.
   */
  public function testQueryById() {
    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);

    $i1_first_name = $this->randomMachineName();
    $i1_last_name = $this->randomMachineName();

    $identity1 = $this->createIdentityWithPersonalName($i1_first_name, $i1_last_name);
    $identity2 = $this->createIdentityWithPersonalName();

    $controller = ServiceController::create($this->container);
    $request = Request::create(
      "/api/identity/query",
      "GET",
      [
        "_format" => "json",
      ]
    );

    $response = $controller->queryData($request);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertInstanceOf(ResourceResponseInterface::class, $response);

    $data = $response->getResponseData();
    $this->assertCount(2, $data);

    // Now Test filtering by ID.
    $request = Request::create(
      "/api/identity/query",
      "GET",
      [
        "_format" => "json",
        "conditions" => [
          '_t' => 'set',
          [
            'class' => FALSE,
            'id' => $identity1->id(),
          ],
        ],
      ]
    );
    $response = $controller->queryData($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertInstanceOf(ResourceResponseInterface::class, $response);

    $data = $response->getResponseData();
    $this->assertCount(1, $data);

    $this->assertEquals("{$i1_first_name} {$i1_last_name}", reset($data)["label"]);
    $this->assertEquals($identity1->uuid(), reset($data)["uuid"]);

    // Now Test filtering by ID in the extended format but do not specify the op
    // as this should default to '='.
    $request = Request::create(
      "/api/identity/query",
      "GET",
      [
        "_format" => "json",
        "conditions" => [
          '_t' => 'set',
          [
            'class' => FALSE,
            'id' => [
              'value' => $identity1->id(),
            ],
          ],
        ],
      ]
    );
    $response = $controller->queryData($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertInstanceOf(ResourceResponseInterface::class, $response);

    $data = $response->getResponseData();
    $this->assertCount(1, $data);

    $this->assertEquals("{$i1_first_name} {$i1_last_name}", reset($data)["label"]);
    $this->assertEquals($identity1->uuid(), reset($data)["uuid"]);

    // Now Test filtering by ID in the extended format specifying '<>' as the
    // op.
    $request = Request::create(
      "/api/identity/query",
      "GET",
      [
        "_format" => "json",
        "conditions" => [
          '_t' => 'set',
          [
            'class' => FALSE,
            'id' => [
              'value' => $identity1->id(),
              'op' => '<>',
            ],
          ],
        ],
      ],
    );
    $response = $controller->queryData($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertInstanceOf(ResourceResponseInterface::class, $response);

    $data = $response->getResponseData();
    $this->assertCount(1, $data);

    $this->assertEquals($identity2->id(), reset($data)["id"]);
    $this->assertEquals($identity2->uuid(), reset($data)["uuid"]);

    // Now Test filtering by ID in the extended format not specifying 'in' as
    // this is the default.
    $request = Request::create(
      "/api/identity/query",
      "GET",
      [
        "_format" => "json",
        "conditions" => [
          '_t' => 'set',
          [
            'class' => FALSE,
            'id' => [
              'value' => [$identity1->id(), $identity2->id()],
            ],
          ],
        ],
      ],
    );
    $response = $controller->queryData($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertInstanceOf(ResourceResponseInterface::class, $response);
    $data = $response->getResponseData();
    $this->assertCount(2, $data);

    // Now Test filtering by ID in the short format not specifying 'in' as
    // this is the default.
    $request = Request::create(
      "/api/identity/query",
      "GET",
      [
        "_format" => "json",
        "conditions" => [
          '_t' => 'set',
          [
            'class' => FALSE,
            'id' => [$identity1->id(), $identity2->id()],
          ],
        ],
      ],
    );
    $response = $controller->queryData($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertInstanceOf(ResourceResponseInterface::class, $response);
    $data = $response->getResponseData();
    $this->assertCount(2, $data);

    // Now Test filtering by ID in the extended format specifying 'not in' as
    // the operator.
    $request = Request::create(
      "/api/identity/query",
      "GET",
      [
        "_format" => "json",
        "conditions" => [
          '_t' => 'set',
          [
            'class' => FALSE,
            'id' => [
              'value' => [$identity1->id(), $identity2->id()],
              'op' => 'NOT IN',
            ],
          ],
        ],
      ],
    );
    $response = $controller->queryData($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertInstanceOf(ResourceResponseInterface::class, $response);
    $data = $response->getResponseData();
    $this->assertCount(0, $data);
  }

}
