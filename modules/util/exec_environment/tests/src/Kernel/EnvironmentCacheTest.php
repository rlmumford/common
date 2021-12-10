<?php

namespace Drupal\Tests\exec_environment\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\exec_environment\Cache\EnvironmentAwareCacheFactory;
use Drupal\exec_environment\Environment;
use Drupal\KernelTests\KernelTestBase;

/**
 * Test for the caching system.
 */
class EnvironmentCacheTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'serialization', 'system', 'datetime', 'user', 'exec_environment',
    'exec_environment_config_test',
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
   * Test that new bins reflect the environment.
   */
  public function testEnvironmentAwareCacheBins() {
    $cache_factory = new EnvironmentAwareCacheFactory(new Settings([]));
    $cache_factory->setContainer($this->container);

    $key = $this->randomMachineName();
    $no_env_data = $this->randomString(30);

    $bin = $cache_factory->get('default');
    $bin->set($key, $no_env_data);

    $this->assertEquals($no_env_data, $bin->get($key)->data);

    /** @var \Drupal\exec_environment\EnvironmentStackInterface $environment_stack */
    $environment_stack = $this->container->get('environment_stack');

    $environment = (new Environment())
      ->addComponent(
        $this->container->get('plugin.manager.exec_environment_component')
          ->createInstance('test_named_cache_bin_suffix', ['name' => 'test_1'])
      );
    $environment_stack->applyEnvironment($environment);

    $this->assertEmpty($bin->get($key));
    $env_data = $this->randomString(30);
    $bin->set($key, $env_data);

    $environment_stack->resetEnvironment();

    $this->assertEquals($no_env_data, $bin->get($key)->data);

    $environment_stack->applyEnvironment($environment);

    $this->assertEquals($env_data, $bin->get($key)->data);
  }

}
