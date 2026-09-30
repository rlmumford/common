<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist\Entity\ChecklistItemInterface;

/**
 * Tests completion timestamps across methods, status writes and upgrades.
 *
 * @group checklist
 */
class ChecklistCompletionTimeTest extends ChecklistItemExecutionTestBase {

  /**
   * Completion methods share the lifecycle and preserve an existing date.
   */
  public function testCompletionLifecycle(): void {
    [$host, $item] = $this->work(record_attempt: FALSE);
    $this->assertTrue($item->get('completed')->isEmpty());
    foreach ([
      ChecklistItemInterface::METHOD_MANUAL,
      ChecklistItemInterface::METHOD_INTERACTIVE,
      ChecklistItemInterface::METHOD_AUTO,
    ] as $method) {
      $this->now += 100;
      $expected = $this->now;
      $item->setComplete($method);
      $this->assertSame($expected, (int) $item->get('completed')->value);
      $item->save();
      $item = $this->reload($item);
      $this->assertSame($expected, (int) $item->get('completed')->value);
      $host->work->checklist->setItem('worker', $item);
      $snapshot = $this->container->get('checklist.item_reader')->read($host, 'work', 0, 'worker');
      $this->assertSame($expected, $snapshot['completed']);
      $this->now += 10;
      $item->setComplete($method)->save();
      $item = $this->reload($item);
      $this->assertSame($expected, (int) $item->get('completed')->value);
      $item->setIncomplete()->save();
      $item = $this->reload($item);
      $this->assertTrue($item->get('completed')->isEmpty());
    }
    $item->setComplete()->save();
    $item->setFailed()->save();
    $this->assertTrue($this->reload($item)->get('completed')->isEmpty());
    $snapshot = $this->container->get('checklist.item_reader')->read($host, 'work', 0, 'worker');
    $this->assertArrayHasKey('completed', $snapshot);
  }

  /**
   * Presave/direct status writes are normalized at the storage boundary.
   */
  public function testDirectStatusWrites(): void {
    [, $item] = $this->work(record_attempt: FALSE);
    $state = $this->container->get('state');
    $state->set('checklist_state_test.complete_in_presave', TRUE);
    $item->save();
    $item = $this->reload($item);
    $this->assertSame(1000, (int) $item->get('completed')->value);
    $state->set('checklist_state_test.complete_in_presave', FALSE);
    $this->now = 1100;
    $item->save();
    $this->assertSame(1000, (int) $this->reload($item)->get('completed')->value);
    $item->set('status', ChecklistItemInterface::STATUS_NA)->save();
    $item = $this->reload($item);
    $this->assertTrue($item->get('completed')->isEmpty());
    $item->set('status', ChecklistItemInterface::STATUS_COMPLETE)->save();
    $this->assertSame(1100, (int) $this->reload($item)->get('completed')->value);
  }

  /**
   * Installing the field does not fabricate dates for existing completions.
   */
  public function testUpgradeLeavesHistoricalDatesUnknown(): void {
    [, $item] = $this->work(record_attempt: FALSE);
    $manager = $this->container->get('entity.definition_update_manager');
    $manager->uninstallFieldStorageDefinition($manager->getFieldStorageDefinition('completed', 'checklist_item'));
    $this->container->get('database')->update('checklist_item')
      ->fields(['status' => ChecklistItemInterface::STATUS_COMPLETE])
      ->condition('id', $item->id())
      ->execute();
    $this->container->get('module_handler')->loadInclude('checklist', 'install');
    checklist_update_10007();
    checklist_update_10007();
    $item = $this->reload($item);
    $this->assertTrue($item->isComplete());
    $this->assertTrue($item->get('completed')->isEmpty());
    $item->setComplete()->save();
    $item = $this->reload($item);
    $this->assertTrue($item->get('completed')->isEmpty());
    $item->setIncomplete()->save();
    $item->setComplete()->save();
    $this->assertSame(1000, (int) $this->reload($item)->get('completed')->value);
  }

}
