<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist\ChecklistInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Tests completion decisions against current item readiness.
 *
 * @group checklist
 */
class ChecklistCompletionTest extends KernelTestBase {

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
   * Builds a checklist with configurable readiness and action results.
   */
  protected function checklist(array $configurations): ChecklistInterface {
    $owner = User::create(['name' => $this->randomMachineName()]);
    $owner->save();
    $items = [];
    foreach ($configurations as $name => $configuration) {
      $items[$name] = [
        'title' => $name,
        'handler' => 'completion_readiness_test',
        'handler_configuration' => $configuration,
      ];
    }
    $type = $this->container->get('plugin.manager.checklist_type')->createInstance('context_test', ['default_items' => $items]);
    return $type->getChecklist($owner, 'checklist');
  }

  /**
   * Unknown applicability blocks execution and completion until it is known.
   */
  public function testUnknownApplicability(): void {
    $checklist = $this->checklist(['work' => ['applicable' => NULL]]);
    $this->assertNull($checklist->getItem('work')->isApplicable());
    $this->assertFalse($checklist->process());
    $this->assertFalse($checklist->isCompletable());
    $this->assertNull($this->container->get('state')->get('checklist_completion_test.runs'));
    $this->container->get('state')->set('checklist_completion_test.applicability', ['work' => TRUE]);
    $this->assertTrue($checklist->process());
    $this->assertTrue($checklist->getItem('work')->isComplete());
    $this->assertSame(['work'], $this->container->get('state')->get('checklist_completion_test.runs'));
  }

  /**
   * Explicit completion also refuses unknown applicability, even if optional.
   */
  public function testExplicitCompletionRefusesUnknown(): void {
    $checklist = $this->checklist(['work' => ['applicable' => NULL, 'required' => FALSE]]);
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('not ready for completion');
    $checklist->complete();
  }

  /**
   * Optional manual work does not prevent automatic checklist completion.
   */
  public function testOptionalManualWork(): void {
    $checklist = $this->checklist(['work' => ['method' => 'manual', 'required' => FALSE]]);
    $this->assertTrue($checklist->isCompletable());
    $this->assertTrue($checklist->process());
    $this->assertTrue($checklist->getItem('work')->isIncomplete());
    $this->assertNull($this->container->get('state')->get('checklist_completion_test.runs'));
  }

  /**
   * Required work blocks while manual, unactionable, incomplete or failed.
   */
  public function testRequiredUnfinishedWork(): void {
    foreach ([['method' => 'manual'], ['actionable' => FALSE], ['stay_incomplete' => TRUE], ['fail' => TRUE]] as $configuration) {
      $checklist = $this->checklist(['work' => $configuration]);
      $this->assertFalse($checklist->process());
      $this->assertFalse($checklist->isCompletable());
      $this->assertFalse($checklist->getItem('work')->isComplete());
      if (isset($configuration['fail'])) {
        $this->assertTrue($checklist->getItem('work')->isFailed());
      }
    }
  }

  /**
   * Definite non-applicability skips work without executing or completing it.
   */
  public function testNotApplicable(): void {
    $checklist = $this->checklist(['work' => ['applicable' => FALSE]]);
    $this->assertFalse($checklist->getItem('work')->isApplicable());
    $this->assertTrue($checklist->process());
    $this->assertTrue($checklist->getItem('work')->isIncomplete());
    $this->assertNull($this->container->get('state')->get('checklist_completion_test.runs'));
  }

  /**
   * Later actions can remove an earlier requirement in the same pass.
   */
  public function testCompletionReevaluatesEarlierItems(): void {
    $checklist = $this->checklist([
      'earlier' => ['method' => 'manual'],
      'later' => ['applicability_updates' => ['earlier' => FALSE]],
    ]);
    $this->assertFalse($checklist->isCompletable());
    $this->assertTrue($checklist->process());
    $this->assertTrue($checklist->isCompletable());
    $this->assertTrue($checklist->getItem('earlier')->isIncomplete());
    $this->assertSame(['later'], $this->container->get('state')->get('checklist_completion_test.runs'));
  }

  /**
   * Newly applicable automatic work runs before completing in this request.
   */
  public function testNewRequirementRunsBeforeCompletion(): void {
    $checklist = $this->checklist([
      'earlier' => ['applicable' => FALSE],
      'later' => ['applicability_updates' => ['earlier' => TRUE]],
    ]);
    $this->assertTrue($checklist->process());
    $this->assertTrue($checklist->getItem('earlier')->isComplete());
    $this->assertTrue($checklist->getItem('later')->isComplete());
    $this->assertSame(['later', 'earlier'], $this->container->get('state')->get('checklist_completion_test.runs'));
    $this->assertTrue($checklist->process());
    $this->assertSame(['later', 'earlier'], $this->container->get('state')->get('checklist_completion_test.runs'));
  }

  /**
   * Newly required manual work still prevents premature completion.
   */
  public function testNewManualRequirementBlocksCompletion(): void {
    $checklist = $this->checklist([
      'earlier' => ['applicable' => FALSE, 'method' => 'manual'],
      'later' => ['applicability_updates' => ['earlier' => TRUE]],
    ]);
    $this->assertFalse($checklist->process());
    $this->assertTrue($checklist->getItem('earlier')->isIncomplete());
    $this->assertFalse($checklist->isCompletable());
    $this->assertSame(['later'], $this->container->get('state')->get('checklist_completion_test.runs'));
  }

}
