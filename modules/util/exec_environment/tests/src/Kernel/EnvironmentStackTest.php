<?php

namespace Drupal\Tests\exec_environment\Kernel;

use Drupal\exec_environment\Environment;
use Drupal\exec_environment\Plugin\ExecEnvironment\Component\CurrentUserComponentInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests for the environment stack.
 */
class EnvironmentStackTest extends KernelTestBase {
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'serialization', 'system', 'datetime', 'user', 'exec_environment',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('user');
  }

  /**
   * All round test for the environment stack.
   */
  public function testEnvironmentStack() {
    $user_1 = $this->createUser();
    $user_2 = $this->createUser();

    $this->setCurrentUser($user_1);

    /** @var \Drupal\exec_environment\EnvironmentStackInterface $environment_stack */
    $environment_stack = $this->container->get('environment_stack');
    $environment = $environment_stack->getActiveEnvironment();

    $this->assertNotNull($environment);
    $this->assertCount(1, $environment->getComponents());

    $components = $environment->getComponents(CurrentUserComponentInterface::class);
    $this->assertCount(1, $components);
    $config = reset($components)->getConfiguration();
    $this->assertArrayHasKey('user', $config);
    $this->assertEquals($config['user']->id(), $user_1->id());

    $new_environment = (new Environment())
      ->addComponent(
        $this->container->get('plugin.manager.exec_environment_component')
          ->createInstance('configured_current_user', ['user' => $user_2])
      );
    $this->assertCount(1, $new_environment->getComponents(CurrentUserComponentInterface::class));

    $this->assertEquals($user_1->id(), $this->container->get('current_user')->id());

    $environment_stack->applyEnvironment($new_environment);
    $this->assertEquals($user_2->id(), $this->container->get('current_user')->id());

    $environment_stack->resetEnvironment();
    $this->assertEquals($user_1->id(), $this->container->get('current_user')->id());
  }

}
