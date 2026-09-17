<?php

namespace Drupal\Tests\service\Kernel;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\service\Entity\Service;
use Drupal\service\ServiceInterface;

/**
 * Tests lifecycle storage and migration without guessing inactive history.
 *
 * @group service
 */
class ServiceStatusTest extends ServiceKernelTestBase {

  /**
   * New services start in draft and parent changes do not change children.
   */
  public function testStatusesAndIndependentChildren(): void {
    $parent = $this->createService();
    $child = $this->createService($parent);
    $this->assertSame(ServiceInterface::STATUS_DRAFT, $parent->getStatus());
    foreach (array_keys(Service::statusOptionsList()) as $status) {
      $parent->set('status', $status)->save();
      $storage = $this->container->get('entity_type.manager')->getStorage('service');
      $storage->resetCache();
      $this->assertSame($status, $storage->load($parent->id())->getStatus());
      $this->assertSame(ServiceInterface::STATUS_DRAFT, $storage->load($child->id())->getStatus());
    }
  }

  /**
   * Trusted direct saves also reject missing and unsupported statuses.
   */
  public function testInvalidStatuses(): void {
    $service = $this->createService();
    foreach ([NULL, '', 'finished'] as $status) {
      $service->set('status', $status);
      try {
        $service->save();
        $this->fail('Storage accepted an invalid status.');
      }
      catch (EntityStorageException $exception) {
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception->getPrevious());
      }
      $this->assertSame(ServiceInterface::STATUS_DRAFT, Service::load($service->id())->getStatus());
    }
  }

  /**
   * Current records and old revisions each migrate using their own boolean.
   */
  public function testLegacyMigration(): void {
    $active = Service::create(['type' => 'work', 'state' => TRUE]);
    $active->save();
    $changed = Service::create(['type' => 'work', 'state' => TRUE]);
    $changed->save();
    $active_revision = $changed->getRevisionId();
    $changed->setNewRevision(TRUE);
    $changed->set('state', FALSE)->save();
    $inactive_revision = $changed->getRevisionId();
    $inactive = Service::create(['type' => 'work', 'state' => FALSE]);
    $inactive->save();

    // Recreate the pre-update schema, retaining the legacy records/revisions.
    $database = $this->container->get('database');
    foreach (['service', 'service_revision'] as $table) {
      $database->update($table)->fields(['status' => NULL])->execute();
    }
    $manager = $this->container->get('entity.definition_update_manager');
    $manager->uninstallFieldStorageDefinition($manager->getFieldStorageDefinition('status', 'service'));
    $this->assertFalse($database->schema()->fieldExists('service', 'status'));
    $this->disableModules(['options']);
    $this->container->get('module_handler')->loadInclude('service', 'install');
    $this->assertStringContainsString('2 current services', (string) service_update_10002());

    $storage = $this->container->get('entity_type.manager')->getStorage('service');
    $this->assertSame(ServiceInterface::STATUS_ACTIVE, $storage->load($active->id())->getStatus());
    $this->assertNull($storage->load($changed->id())->getStatus());
    $this->assertNull($storage->load($inactive->id())->getStatus());
    $this->assertSame(ServiceInterface::STATUS_ACTIVE, $storage->loadRevision($active_revision)->getStatus());
    $this->assertNull($storage->loadRevision($inactive_revision)->getStatus());
    $this->assertSame('1', (string) $storage->loadRevision($active_revision)->get('state')->value);
    $this->assertSame('0', (string) $storage->loadRevision($inactive_revision)->get('state')->value);

    // Mapping a current record creates a revision; ambiguous history stays put.
    $mapped = $storage->load($inactive->id());
    $legacy_revision = $mapped->getRevisionId();
    $mapped->setNewRevision(TRUE);
    $mapped->set('status', ServiceInterface::STATUS_CANCELLED)->save();
    $this->assertNull($storage->loadRevision($legacy_revision)->getStatus());
    $this->assertSame('0', (string) $mapped->get('state')->value);
    service_update_10002();
    $this->assertSame(ServiceInterface::STATUS_CANCELLED, $storage->load($inactive->id())->getStatus());
    $this->assertSame(ServiceInterface::STATUS_DRAFT, $this->createService()->getStatus());
  }

}
