<?php

namespace Drupal\Tests\typed_data_plus\Kernel;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\typed_data_context_assignment\Controller\AutocompleteController;
use Symfony\Component\HttpFoundation\Request;
use Drupal\typed_data_plus\Plugin\Context\ContextHandler;
use Drupal\typed_data_plus\Plugin\Context\DataContextDefinition;
use Drupal\user\Entity\User;

/**
 * Tests Drupal conditions and selector mappings, including global providers.
 *
 * @group typed_data_plus
 */
class ConditionPluginTest extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'typed_data', 'typed_data_plus', 'typed_data_context_assignment'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
  }

  /**
   * Tests configuration without runtime values, negation and fresh execution.
   */
  public function testConditionContract(): void {
    $plugin = $this->container->get('plugin.manager.condition')->createInstance('condition_string', [
      'condition_string' => 'value == 0',
      'negate' => TRUE,
      'context_mapping' => ['value' => 'input.0'],
    ]);
    $definition = ContextDefinition::create('integer')->setRequired(FALSE);
    $plugin->setExpectedContexts(['value' => $definition]);
    $plugin->validate();
    $form_state = new FormState();
    $form = $plugin->buildConfigurationForm([], $form_state);
    $this->assertSame('value == 0', $form['condition_string']['#default_value']);
    $source_definition = DataContextDefinition::fromDataDefinition(ListDataDefinition::create('integer'));
    $source = new Context($source_definition, [0]);
    $source->addCacheableDependency((new CacheableMetadata())->setCacheTags(['source:1'])->setCacheMaxAge(15));
    $plugin->setRuntimeContexts(['input' => $source]);
    $this->assertTrue($plugin->evaluate());
    $this->assertFalse($plugin->execute());
    $this->assertContains('source:1', $plugin->getCacheTags());
    $this->assertSame(15, $plugin->getCacheMaxAge());
    $plugin->setRuntimeContexts(['input' => new Context($source_definition, [2])]);
    $this->assertTrue($plugin->execute());
    $this->assertNotContains('source:1', $plugin->getCacheTags());
    $plugin->setRuntimeContexts([]);
    $this->assertTrue($plugin->execute());
    $this->assertArrayNotHasKey('expected_contexts', $plugin->getConfiguration());
  }

  /**
   * Tests AND/OR composition with a core condition and nested negation.
   */
  public function testGroups(): void {
    $manager = $this->container->get('plugin.manager.condition');
    $plugin = $manager->createInstance('condition_and', [
      'conditions' => [
        ['id' => 'condition_string', 'condition_string' => 'count == 0'],
        [
          'id' => 'condition_or',
          'conditions' => [
          ['id' => 'condition_string', 'condition_string' => 'NEVER'],
          ['id' => 'user_role', 'roles' => ['staff'], 'context_mapping' => ['user' => 'actor']],
          ],
        ],
      ],
    ]);
    $actor_definition = ContextDefinition::create('entity:user');
    $count_definition = ContextDefinition::create('integer');
    $actor = new Context($actor_definition, User::create(['uid' => 1, 'roles' => ['staff']]));
    $actor->addCacheableDependency((new CacheableMetadata())->setCacheTags(['actor:1'])->setCacheContexts(['user.roles']));
    $plugin->setExpectedContexts(['actor' => $actor_definition, 'count' => $count_definition]);
    $plugin->setRuntimeContexts(['actor' => $actor, 'count' => new Context($count_definition, 0)]);
    $this->assertTrue($plugin->execute());
    $this->assertContains('actor:1', $plugin->getCacheTags());
    $this->assertContains('user.roles', $plugin->getCacheContexts());
    $this->assertContains('user', $plugin->calculateDependencies()['module']);
    $this->assertTrue($manager->createInstance('condition_and')->execute());
    $this->assertFalse($manager->createInstance('condition_or')->execute());
    $this->assertTrue($manager->createInstance('condition_or', ['negate' => TRUE])->execute());
    $this->expectException(PluginNotFoundException::class);
    $manager->createInstance('condition_or', [
      'conditions' => [
      ['id' => 'condition_string', 'condition_string' => ''],
      ['id' => 'missing_plugin'],
      ],
    ])->execute();
  }

  /**
   * Tests lazy global values, provider-qualified paths and local precedence.
   */
  public function testGlobalProviderSelectors(): void {
    $definition = ContextDefinition::create('string');
    $global = '@example.provider:context.with.dots';
    $runtime = new Context($definition, 'hello');
    $runtime->addCacheableDependency((new CacheableMetadata())->setCacheTags(['provider:1']));
    $repository = $this->createMock(ContextRepositoryInterface::class);
    $repository->method('getAvailableContexts')->willReturn([
      $global => new Context($definition),
      '@unused.provider:other' => new Context($definition),
    ]);
    $repository->expects($this->once())->method('getRuntimeContexts')->with([$global])->willReturn([$global => $runtime]);
    $handler = new ContextHandler($this->container->get('typed_data_plus.data_fetcher'), $repository);
    $this->container->set('typed_data_plus.context_handler', $handler);
    $plugin = $this->container->get('plugin.manager.condition')->createInstance('condition_string', [
      'condition_string' => "value == 'HELLO'",
      'context_mapping' => ['value' => $global . '|upper'],
    ]);
    $plugin->setExpectedContexts(['value' => $definition]);
    $plugin->validate();
    $matches = $handler->getMatchingContexts([], $definition);
    $this->assertArrayHasKey($global, $matches);
    $plugin->setRuntimeContexts([]);
    $this->assertTrue($plugin->execute());
    $this->assertContains('provider:1', $plugin->getCacheTags());
    // Explicitly supplied provider contexts win, including in queue workers.
    $plugin->setRuntimeContexts([$global => new Context($definition, 'local')]);
    $this->assertFalse($plugin->execute());
    $this->assertNotContains('provider:1', $plugin->getCacheTags());
  }

  /**
   * Tests the existing site's adapter and standard Drupal assignment widget.
   */
  public function testStandardSelectionAndFalseValues(): void {
    $handler = $this->container->get('context.handler');
    $this->assertInstanceOf(ContextHandler::class, $handler);
    $definition = ContextDefinition::create('boolean')->setRequired(FALSE);
    $source_definition = DataContextDefinition::fromDataDefinition(ListDataDefinition::create('boolean'));
    $source = new Context($source_definition, [FALSE]);
    $matches = $handler->getMatchingContexts(['items' => $source], $definition);
    $this->assertArrayHasKey('items.0', $matches);
    $this->assertArrayNotHasKey('items', $matches);
    $plugin = $this->container->get('plugin.manager.condition')->createInstance('condition_string', [
      'condition_string' => 'value == false',
      'context_mapping' => ['value' => 'items.0'],
    ]);
    $plugin->setExpectedContexts(['value' => $definition]);
    $state = new FormState();
    $state->setTemporaryValue('gathered_contexts', ['items' => $source]);
    $form = $plugin->buildConfigurationForm([], $state);
    $this->assertArrayHasKey('items.0', $form['context_mapping']['value']['#options']);
    $plugin->setRuntimeContexts(['items' => $source]);
    $this->assertTrue($plugin->execute());
    $this->expectException(ContextException::class);
    $plugin->setRuntimeContexts(['items' => new Context(DataContextDefinition::fromDataDefinition(ListDataDefinition::create('string')), ['wrong type'])]);
  }

  /**
   * Tests filters on missing root data and required context checks.
   */
  public function testDefaultFilterAndMissingRequiredContext(): void {
    $definition = ContextDefinition::create('string');
    $plugin = $this->container->get('plugin.manager.condition')->createInstance('condition_string', [
      'condition_string' => "value == 'fallback'",
      'context_mapping' => ['value' => "source|default('fallback')"],
    ]);
    $plugin->setExpectedContexts(['value' => $definition]);
    $plugin->setRuntimeContexts(['source' => new Context($definition)]);
    $this->assertTrue($plugin->execute());
    $this->expectException(ContextException::class);
    $plugin->setRuntimeContexts([]);
  }

  /**
   * Tests recursive configuration schema and dotted global autocomplete roots.
   */
  public function testSchemaAndGlobalAutocomplete(): void {
    $this->assertConfigSchema($this->container->get('config.typed'), 'condition.plugin.condition_and', [
      'id' => 'condition_and',
      'negate' => FALSE,
      'conditions' => [
        [
          'id' => 'condition_or',
          'negate' => TRUE,
          'conditions' => [
            ['id' => 'condition_string', 'condition_string' => 'NEVER', 'negate' => FALSE],
          ],
        ],
      ],
    ]);
    $root = '@example.provider:context.with.dots';
    $definition = DataContextDefinition::fromDataDefinition(ListDataDefinition::create('integer'));
    $storage = $this->createMock(KeyValueStoreInterface::class);
    $storage->method('has')->willReturn(TRUE);
    $storage->method('get')->willReturnMap([
      ['required', NULL, ContextDefinition::create('integer')],
      ['available', NULL, [$root => $definition]],
    ]);
    $controller = new AutocompleteController($this->container->get('typed_data_plus.data_fetcher'), $storage);
    $response = $controller->handleAutocomplete(new Request(['q' => $root . '.']), 'required', 'available');
    $suggestions = json_decode($response->getContent(), TRUE);
    $this->assertContains($root . '.0', array_column($suggestions, 'value'));
  }

}
