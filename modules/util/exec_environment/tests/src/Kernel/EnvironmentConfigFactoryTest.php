<?php

namespace Drupal\Tests\exec_environment\Kernel;

use Drupal\Core\Datetime\Entity\DateFormat;
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

  /**
   * Tests that config entities inserted in an environment are loaded correctly.
   */
  public function testConfigEntityInsertedInEnvironment() {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $format_storage */
    $format_storage = $this->container->get('entity_type.manager')->getStorage('date_format');

    $all = $format_storage->loadMultiple();
    $this->assertCount(11, $all);

    $environment = (new Environment())
      ->addComponent(
        $this->container->get('plugin.manager.exec_environment_component')
          ->createInstance('test_named_config_collection', ['collection' => 'test_1'])
      );
    /** @var \Drupal\exec_environment\EnvironmentStackInterface $environment_stack */
    $environment_stack = $this->container->get('environment_stack');
    $environment_stack->applyEnvironment($environment);

    $this->assertCount(11, $format_storage->loadMultiple());
    $format_storage->create([
      'status' => TRUE,
      'id' => 'iso_8601',
      'label' => 'Iso 8601',
      'pattern' => 'c',
    ])->save();
    $this->assertCount(12, $format_storage->loadMultiple());

    $environment_stack->resetEnvironment();

    $this->assertCount(11, $format_storage->loadMultiple());
    $this->assertNull($format_storage->load('iso_8601'));

    $environment_stack->applyEnvironment($environment);
    $this->assertInstanceOf(DateFormat::class, $format_storage->load('iso_8601'));
    $environment_stack->resetEnvironment();
    $this->assertNull($format_storage->load('iso_8601'));
  }

  /**
   * Test loading and saving config entities.
   *
   * Test that config entities updated in an environment are stored and loaded
   * correctly.
   */
  public function testConfigEntityUpdatedInEnvironment() {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $format_storage */
    $format_storage = $this->container->get('entity_type.manager')->getStorage('date_format');

    /** @var \Drupal\Core\Datetime\Entity\DateFormat $format */
    $format = $format_storage->load('html_date');
    $this->assertEquals($format->get('pattern'), 'Y-m-d');

    $environment = (new Environment())
      ->addComponent(
        $this->container->get('plugin.manager.exec_environment_component')
          ->createInstance('test_named_config_collection', ['collection' => 'test_1'])
      );
    /** @var \Drupal\exec_environment\EnvironmentStackInterface $environment_stack */
    $environment_stack = $this->container->get('environment_stack');
    $environment_stack->applyEnvironment($environment);

    $format->set('pattern', 'm/d/Y')->save();
    $format = $format_storage->load($format->id());
    $this->assertEquals('m/d/Y', $this->container->get('config.factory')->get('core.date_format.html_date')->get('pattern'));
    $this->assertEquals('m/d/Y', $format->get('pattern'));
    $environment_stack->resetEnvironment();

    // The saved change should only have applied in the environment.
    $this->assertEquals('Y-m-d', $format_storage->load($format->id())->get('pattern'));

    $format_storage->load($format->id())->set('pattern', 'd/m/Y')->save();
    $this->assertEquals('d/m/Y', $format_storage->load($format->id())->get('pattern'));

    $environment_stack->applyEnvironment($environment);
    $this->assertEquals('m/d/Y', $format_storage->load($format->id())->get('pattern'));
    $environment_stack->resetEnvironment();
    $this->assertEquals('d/m/Y', $format_storage->load($format->id())->get('pattern'));
  }

  /**
   * Test deleting config entities in an environment.
   */
  public function testConfigEntityDeletedInEnvironment() {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $format_storage */
    $format_storage = $this->container->get('entity_type.manager')->getStorage('date_format');

    /** @var \Drupal\Core\Datetime\Entity\DateFormat $format */
    $format = $format_storage->load('html_date');
    $this->assertEquals($format->get('pattern'), 'Y-m-d');

    $environment = (new Environment())
      ->addComponent(
        $this->container->get('plugin.manager.exec_environment_component')
          ->createInstance('test_named_config_collection', ['collection' => 'test_1'])
      );
    /** @var \Drupal\exec_environment\EnvironmentStackInterface $environment_stack */
    $environment_stack = $this->container->get('environment_stack');
    $environment_stack->applyEnvironment($environment);

    $format->set('pattern', 'm/d/Y')->save();
    $format = $format_storage->load($format->id());
    $this->assertEquals('m/d/Y', $format->get('pattern'));
    $environment_stack->resetEnvironment();

    $this->assertEquals('Y-m-d', $format_storage->load($format->id())->get('pattern'));
    $environment_stack->applyEnvironment($environment);

    // Delete acts like revert.
    $this->assertEquals('m/d/Y', $format_storage->load('html_date')->get('pattern'));
    $format_storage->load('html_date')->delete();
    $this->assertEquals('Y-m-d', $format_storage->load('html_date')->get('pattern'));

    $environment_stack->resetEnvironment();
    $this->assertEquals('Y-m-d', $format_storage->load('html_date')->get('pattern'));
  }

}
