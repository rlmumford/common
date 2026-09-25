<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\checklist\Workspace\ChecklistWorkspaceAddress;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests durable checklist workspace ownership and fencing generations.
 *
 * @group checklist
 */
class ChecklistWorkspaceStorageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'checklist', 'typed_data', 'typed_data_plus'];

  /**
   * A deterministic worker clock.
   *
   * @var int
   */
  protected int $now = 1000;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('checklist', ['checklist_workspace']);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturnCallback(fn() => $this->now);
    $this->container->set('datetime.time', $time);
  }

  /**
   * The lease blocks another owner and fences stale generations.
   */
  public function testOwnershipAndFencing(): void {
    $storage = $this->container->get('checklist.workspace_storage');
    $address = new ChecklistWorkspaceAddress('user', 'host-uuid', 'work', 0, 'main', 'instance-uuid-1');
    $first = $storage->acquire($address, 11, 30);
    $this->assertSame(1, $first->generation);
    $this->assertTrue($storage->isCurrent($first));
    $this->assertConflict(fn() => $storage->acquire($address, 22, 30));

    $renewed = $storage->renew($first, 60);
    $this->assertSame(1060, $renewed->expires);
    $storage->release($renewed);
    $this->assertFalse($storage->isCurrent($renewed));

    $second = $storage->acquire($address, 22, 30);
    $this->assertSame(2, $second->generation);
    $this->assertConflict(fn() => $storage->renew($first));
  }

  /**
   * Expired leases can be acquired as a new generation.
   */
  public function testExpiryAllowsReacquisition(): void {
    $storage = $this->container->get('checklist.workspace_storage');
    $address = new ChecklistWorkspaceAddress('user', 'host-uuid', 'work', 0, 'main', 'instance-uuid-1');
    $first = $storage->acquire($address, 11, 30);
    $this->now = 1030;
    $second = $storage->acquire($address, 22, 30);
    $this->assertSame(2, $second->generation);
    $this->assertFalse($storage->isCurrent($first));
    $this->assertTrue($storage->isCurrent($second));
  }

  /**
   * Reacquiring an active lease as the same owner preserves its fence.
   */
  public function testSameOwnerAcquisitionIsIdempotent(): void {
    $storage = $this->container->get('checklist.workspace_storage');
    $address = new ChecklistWorkspaceAddress('user', 'host-uuid', 'work', 0, 'main', 'instance-uuid-1');
    $first = $storage->acquire($address, 11, 30);
    $version = $storage->advanceVersion($first, 0);
    $this->now = 1010;

    $reacquired = $storage->acquire($address, 11, 60);

    $this->assertSame($first->generation, $reacquired->generation);
    $this->assertSame($version->version, $reacquired->version);
    $this->assertSame(1070, $reacquired->expires);
    $this->assertTrue($storage->isCurrent($version));
  }

  /**
   * Version advancement rejects stale tabs and expired owners atomically.
   */
  public function testVersionFence(): void {
    $storage = $this->container->get('checklist.workspace_storage');
    $address = new ChecklistWorkspaceAddress('user', 'host-uuid', 'work', 0, 'main', 'instance-uuid-1');
    $lease = $storage->acquire($address, 11, 30);
    $next = $storage->advanceVersion($lease, 0);
    $this->assertSame(1, $next->version);
    $this->assertConflict(fn() => $storage->advanceVersion($lease, 0));
    $this->now = 1030;
    $this->assertConflict(fn() => $storage->advanceVersion($next, 1));
  }

  /**
   * A moved checklist has the same identity; a replacement has another.
   */
  public function testInstanceIdentitySurvivesReorderingAndChangesOnReplacement(): void {
    $first = new ChecklistWorkspaceAddress('user', 'host-uuid', 'multiple', 0, 'multiple:0', 'first-instance');
    $moved = new ChecklistWorkspaceAddress('user', 'host-uuid', 'multiple', 1, 'multiple:1', 'first-instance');
    $replacement = new ChecklistWorkspaceAddress('user', 'host-uuid', 'multiple', 0, 'multiple:0', 'replacement-instance');

    $this->assertSame($first->id(), $moved->id());
    $this->assertNotSame($first->id(), $replacement->id());
  }

  /**
   * Asserts a workspace conflict without depending on PHPUnit's closure text.
   */
  protected function assertConflict(callable $operation): void {
    $this->expectException(ChecklistAttemptConflictException::class);
    $operation();
  }

}
