<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist_state_test\Plugin\ChecklistItemHandler\Iteration;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Shared persisted-item fixtures for iteration submission and execution tests.
 */
abstract class ChecklistIterationTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'text', 'filter', 'options', 'entity',
    'checklist', 'checklist_context_test', 'checklist_state_test',
    'checklist_resolver_test', 'plugin_reference', 'typed_data', 'typed_data_plus',
    'typed_data_reference', 'typed_data_context_assignment', 'inline_entity_form',
  ];

  /**
   * Controlled current time, independent of request time.
   *
   * @var int
   */
  protected int $now = 1000;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('checklist', ['checklist_attempt', 'checklist_attempt_head', 'checklist_attempt_event']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('checklist_item');
    $this->installConfig(['system', 'user']);
    FieldStorageConfig::create([
      'field_name' => 'work',
      'entity_type' => 'user',
      'type' => 'checklist',
    ])->save();
    FieldConfig::create(['field_name' => 'work', 'entity_type' => 'user', 'bundle' => 'user', 'translatable' => FALSE])->save();
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturnCallback(fn() => $this->now);
    $time->method('getRequestTime')->willReturn(1000);
    $this->container->set('datetime.time', $time);
    $caller = User::create(['name' => 'Caller', 'status' => 1]);
    $caller->save();
    $this->container->get('current_user')->setAccount($caller);
    Iteration::$calls = [];
    Iteration::$during = NULL;
  }

  /**
   * Creates a saved autonomous item, owned/executed by a non-admin user.
   */
  protected function work(array $configuration = [], ?int $executor = NULL, string $name = 'Target', bool $record_attempt = TRUE): array {
    $host = User::create([
      'name' => $name,
      'status' => 1,
      'work' => [
        'id' => 'context_test',
        'configuration' => [
          'default_items' => [
            'worker' => [
              'title' => 'Worker',
              'handler' => 'iteration_test',
              'handler_configuration' => $configuration + ['context_mapping' => ['value' => 'checklist:entity.name.value']],
            ],
          ],
        ],
      ],
    ]);
    $host->save();
    $item = $host->work->checklist->getItem('worker');
    $item->save();
    $attempt = $record_attempt
      ? $this->container->get('checklist.attempt_journal')->create($item, 1, $executor ?? (int) $host->id(), ChecklistAttempt::ACTION)
      : NULL;
    return [$host, $item, $attempt];
  }

  /**
   * Reloads item state after a commit or rollback.
   */
  protected function reload($item) {
    return $this->container->get('entity_type.manager')->getStorage('checklist_item')->loadUnchanged($item->id());
  }

}
