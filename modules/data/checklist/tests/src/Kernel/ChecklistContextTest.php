<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist\ChecklistInterface;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Tests typed outcomes and automatic processing without a browser or Task.
 *
 * @group checklist
 */
class ChecklistContextTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'text', 'filter', 'options', 'entity',
    'checklist', 'checklist_context_test', 'plugin_reference', 'typed_data',
    'typed_data_plus', 'typed_data_reference', 'typed_data_context_assignment',
    'inline_entity_form',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('checklist_item');
    $this->installConfig(['system', 'user']);
  }

  /**
   * Builds a real checklist with a producer followed by a context consumer.
   */
  protected function checklist(array $producer = [], array $consumer = []): ChecklistInterface {
    $owner = User::create(['name' => 'Owner', 'mail' => 'owner@example.com']);
    $owner->save();
    $type = $this->container->get('plugin.manager.checklist_type')->createInstance('context_test', [
      'default_items' => [
        'source' => ['title' => 'Source', 'handler' => 'context_producer', 'handler_configuration' => $producer],
        'consumer' => [
          'title' => 'Consumer',
          'handler' => 'context_consumer',
          'handler_configuration' => $consumer + ['context_mapping' => ['value' => 'item:source:value']],
        ],
      ],
    ]);
    return $type->getChecklist($owner, 'checklist');
  }

  /**
   * Expected definitions expose complex selectors before any outcomes exist.
   */
  public function testExpectedAndRuntimeContexts(): void {
    $checklist = $this->checklist(['method' => 'manual']);
    $collector = $this->container->get('checklist.context_collector');
    $config = $collector->collectConfigContexts($checklist);
    $runtime = $collector->collectRuntimeContexts($checklist);
    $this->assertSame(array_keys($config), array_keys($runtime));
    $this->assertFalse($config['item:source:value']->hasContextValue());
    $this->assertFalse($runtime['item:source:value']->hasContextValue());
    $this->assertTrue($config['item:source:tags']->getContextDefinition()->isMultiple());
    $this->assertSame('entity:user', $config['item:source:user']->getContextDefinition()->getDataType());
    $matches = $this->container->get('typed_data_plus.context_handler')->getMatchingContexts($config, ContextDefinition::create('string'));
    $this->assertArrayHasKey('item:source:details.label', $matches);
    $this->assertArrayHasKey('item:source:tags.0', $matches);

    $source = $checklist->getItem('source');
    $source->setOutcome('value', 'Ready');
    $source->setOutcome('details', ['label' => 'Nested']);
    $source->setOutcome('tags', ['first', 'second']);
    $source->setOutcome('user', $checklist->getEntity());
    $source->save();
    $runtime = $collector->collectRuntimeContexts($checklist);
    $this->assertSame('Ready', $runtime['item:source:value']->getContextValue());
    $this->assertContains('checklist_item:' . $source->id(), $runtime['item:source:value']->getCacheTags());
    $storage = $this->container->get('entity_type.manager')->getStorage('checklist_item');
    $source = $storage->loadUnchanged($source->id());
    $checklist->setItem('source', $source);
    $runtime = $collector->collectRuntimeContexts($checklist);
    $this->assertSame('Ready', $runtime['item:source:value']->getContextValue());
    $this->assertSame(['label' => 'Nested'], $runtime['item:source:details']->getContextValue());
    $this->assertSame(['first', 'second'], $runtime['item:source:tags']->getContextValue());
    $this->assertEquals($checklist->getEntity()->id(), $runtime['item:source:user']->getContextValue()->id());
  }

  /**
   * A later automatic item receives an earlier item's outcome in the same pass.
   */
  public function testSequentialProcessing(): void {
    $checklist = $this->checklist();
    $this->assertTrue($checklist->process());
    $this->assertSame([['Produced', NULL]], $this->container->get('state')->get('checklist_context_test.runs'));
    $this->assertTrue($checklist->isCompletable());
    $this->assertTrue($checklist->getItem('consumer')->isComplete());
  }

  /**
   * Required values block work and completion; remapping clears stale values.
   */
  public function testMissingAndRemovedValues(): void {
    $checklist = $this->checklist(['method' => 'manual'], [
      'stay_incomplete' => TRUE,
      'context_mapping' => ['value' => 'item:source:value', 'optional' => 'item:source:details.label'],
    ]);
    $source = $checklist->getItem('source');
    // A completed producer may still have an unavailable outcome.
    $source->setComplete()->save();
    $this->assertFalse($checklist->process());
    $this->assertFalse($checklist->isCompletable());
    $this->assertNull($this->container->get('state')->get('checklist_context_test.runs'));
    $this->assertFalse($checklist->getItem('consumer')->isFailed());

    $source->setOutcome('value', 'Available');
    $source->setOutcome('details', ['label' => 'Optional']);
    $this->assertFalse($checklist->process());
    $source->setOutcome('details', NULL);
    $this->assertFalse($checklist->process());
    $this->assertSame([['Available', 'Optional'], ['Available', NULL]], $this->container->get('state')->get('checklist_context_test.runs'));
    $source->setOutcome('value', NULL);
    $this->assertFalse($checklist->process());
    $this->assertFalse($checklist->getItem('consumer')->getHandler()->getContext('value')->hasContextValue());
    $this->assertCount(2, $this->container->get('state')->get('checklist_context_test.runs'));
  }

  /**
   * Selectors use the shared fetcher, including filters and global providers.
   */
  public function testFilteredAndGlobalContexts(): void {
    $checklist = $this->checklist([], [
      'context_mapping' => [
        'value' => 'item:source:value|upper',
        'optional' => '@user.current_user_context:current_user.name.value',
      ],
    ]);
    $this->container->get('current_user')->setAccount($checklist->getEntity());
    $this->assertTrue($checklist->process());
    $this->assertSame([['PRODUCED', 'Owner']], $this->container->get('state')->get('checklist_context_test.runs'));
    $handler = $checklist->getItem('consumer')->getHandler();
    $this->assertContains('user', $handler->getContext('optional')->getCacheContexts());
  }

  /**
   * Explicitly inapplicable items do not need execution contexts.
   */
  public function testInapplicableItem(): void {
    $checklist = $this->checklist();
    $checklist->getItem('consumer')->set('status', 'na');
    $this->assertTrue($checklist->process());
    $this->assertTrue($checklist->isCompletable());
    $this->assertNull($this->container->get('state')->get('checklist_context_test.runs'));
  }

  /**
   * Invalid mappings are configuration errors, not successful skipped work.
   */
  public function testInvalidMapping(): void {
    $checklist = $this->checklist(['method' => 'manual'], ['context_mapping' => ['value' => 'unknown']]);
    $this->expectException(ContextException::class);
    $this->expectExceptionMessage('Assigned contexts were not satisfied');
    $checklist->process();
  }

}
