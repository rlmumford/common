<?php

namespace Drupal\Tests\exec_environment\Kernel;

use Drupal\exec_environment\Environment;
use Drupal\exec_environment\EnvironmentConfigFactory;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests for the environmental config factory.
 */
class EnvironmentConfigFactoryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'serialization', 'system', 'user', 'exec_environment',
    'exec_environment_config_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig(['system']);
  }

  /**
   * Test the environmental config system.
   */
  public function testEnvironmentalConfig() {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $this->container->get('config.factory');
    $this->assertInstanceOf(EnvironmentConfigFactory::class, $config_factory);

    $this->assertEquals('', $config_factory->get('system.site')->get('name'));

    $environment = (new Environment())
      ->addComponent(
        $this->container->get('plugin.manager.exec_environment_component')
          ->createInstance('test_named_config_collection', ['collection' => 'test_1'])
      );
    /** @var \Drupal\exec_environment\EnvironmentStackInterface $environment_stack */
    $environment_stack = $this->container->get('environment_stack');
    $environment_stack->applyEnvironment($environment);

    $this->assertEquals('', $config_factory->get('system.site')->get('name'));

    $environment_stack->resetEnvironment();
    $this->assertEquals('', $config_factory->get('system.site')->get('name'));

    $name = $this->randomString();
    $site_settings = clone $config_factory->getEditable('system.site');
    $site_settings->set('name', $name);
    $this->container->get('config.storage')->createCollection('environment:test_2')->write('system.site', $site_settings->get());

    // Even though we have saved a version of the config in a different
    // collection, as there is no environment set we should still get the
    // default empty value.
    $this->assertEquals('', $config_factory->get('system.site')->get('name'));

    $environment = (new Environment())
      ->addComponent(
        $this->container->get('plugin.manager.exec_environment_component')
          ->createInstance('test_named_config_collection', ['collection' => 'test_2'])
      );
    $environment_stack->applyEnvironment($environment);

    $this->assertEquals($name, $config_factory->get('system.site')->get('name'));

    $environment_stack->resetEnvironment();
    $this->assertEquals('', $config_factory->get('system.site')->get('name'));

    $name_2 = $this->randomString();
    $environment_stack->applyEnvironment($environment);

    $site_settings = $config_factory->getEditable('system.site');
    $this->assertEquals($name, $config_factory->get('system.site')->get('name'));

    $site_settings->set('name', $name_2);
    $site_settings->save();

    $this->assertEquals($name_2, $site_settings->get('name'));
    $this->assertEquals($name_2, $config_factory->get('system.site')->get('name'));

    $environment_stack->resetEnvironment();
    $this->assertEquals('', $config_factory->get('system.site')->get('name'));

    $environment_stack->applyEnvironment($environment);
    $this->assertEquals($name_2, $config_factory->get('system.site')->get('name'));
    $config_factory->getEditable('system.site')->delete();
    $this->assertEquals('', $config_factory->get('system.site')->get('name'));
    $environment_stack->resetEnvironment();

    $this->assertCount(11, $config_factory->listAll('core.date_format'));
    $this->container->get('config.storage')->createCollection('environment:test_2')->write('core.date_format.iso_8601', [
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [],
      'id' => 'iso_8601',
      'label' => 'Iso 8601',
      'locked' => TRUE,
      'pattern' => 'c',
    ]);
    $environment_stack->applyEnvironment($environment);
    $this->assertCount(12, $config_factory->listAll('core.date_format'));
    $environment_stack->resetEnvironment();
    $this->assertCount(11, $config_factory->listAll('core.date_format'));
  }

}
