<?php

namespace Drupal\Tests\checklist_flexiform\Kernel;

use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist_flexiform_test\Plugin\FormDataProvider\Pending;
use Drupal\flexiform\Api\InvalidInputException;
use Drupal\flexiform\Entity\FormDefinition;
use Drupal\Tests\checklist\Kernel\ChecklistItemExecutionTestBase;
use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\user\Entity\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Exercises saved Flexiform checklist work through the shared operation host.
 *
 * @group checklist_flexiform
 */
class FlexiformItemTest extends ChecklistItemExecutionTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ctools', 'token', 'flexiform', 'checklist_flexiform', 'checklist_flexiform_test'];

  /**
   * Creates a form that edits its host, publishing that entity for later work.
   */
  protected function formWork(bool $wizard = FALSE, bool $referenced = FALSE, bool $persist = TRUE): array {
    $configuration = [
      'data' => [
        'person' => [
          'plugin' => 'provided',
          'entity_type' => 'user',
          'bundle' => 'user',
          'save_on_submit' => TRUE,
        ],
      ],
      'components' => [
        'name' => [
          'component_type' => 'typed_data',
          'context' => 'person',
          'path' => 'name.0.value',
          'label' => 'Name',
        ],
      ],
    ];
    if ($wizard) {
      $configuration['components']['language'] = [
        'component_type' => 'typed_data',
        'context' => 'person',
        'path' => 'langcode.0.value',
        'label' => 'Language',
      ];
      $configuration['pages'] = [
        ['label' => 'Name', 'components' => ['name']],
        ['label' => 'Contact', 'components' => ['language']],
      ];
    }
    $form = ['plugin' => $wizard ? 'wizard' : 'standard', 'configuration' => $configuration];
    if ($referenced) {
      FormDefinition::create([
        'id' => 'profile',
        'label' => 'Profile',
        'plugin' => $form['plugin'],
        'configuration' => $configuration,
      ])->save();
      $form = ['plugin' => 'referenced', 'configuration' => ['form_id' => 'profile']];
    }
    $host = User::create([
      'name' => 'Original',
      'status' => 1,
      'work' => [
        'id' => 'context_test',
        'configuration' => [
          'default_items' => [
            'form' => [
              'title' => 'Update details',
              'handler' => 'flexiform',
              'handler_configuration' => [
                'form' => $form,
                'context_mapping' => ['person' => 'checklist:entity'],
                'outcomes' => ['person' => 'person'],
              ],
            ],
            'consumer' => [
              'title' => 'Use details',
              'handler' => 'context_consumer',
              'handler_configuration' => ['context_mapping' => ['value' => 'item:form:person.name.value']],
            ],
          ],
        ],
      ],
    ]);
    $host->save();
    $item = $host->work->checklist->getItem('form');
    if ($persist) {
      $item->save();
    }
    $this->assertConfigSchema($this->container->get('config.typed'), 'checklist_item_handler.flexiform', $item->getHandler()->getConfiguration());
    return [$host, $item];
  }

  /**
   * Separate pages resolve the same stored item on first interaction.
   */
  public function testVirtualItemIdentity(): void {
    [$host, $first] = $this->formWork(persist: FALSE);
    $fresh = $this->container->get('entity_type.manager')->getStorage('user')->loadUnchanged($host->id());
    $second = $fresh->work->checklist->getItem('form');
    $repository = $this->container->get('checklist.tempstore_repository');
    $host->work->checklist->getItem('consumer')->set('title', 'Unsaved label');
    $repository->set($host->work->checklist);
    $editor = $this->container->get('checklist_flexiform.editor');
    $this->assertSame('new', $editor->describe($first)['status']);
    $this->assertTrue($first->isNew());
    $ready = $editor->operate($first, 'start', ['revision' => 0]);
    $this->assertSame($ready['id'], $editor->describe($second)['id']);
    $restored = $repository->get($fresh->work->checklist)->getItem('form');
    $this->assertFalse($restored->isNew());
    $this->assertSame($ready['id'], $restored->uuid());
    $this->assertFalse($restored->get('state')->isEmpty());
    $storage = $this->container->get('entity_type.manager')->getStorage('checklist_item');
    $this->assertCount(1, $storage->loadByProperties(['uuid' => $ready['id']]));
    $editor->operate($restored, 'form/submit', ['revision' => $ready['revision'], 'input' => []]);
    $restored = $repository->get($fresh->work->checklist);
    $this->assertTrue($restored->getItem('form')->isComplete());
    $this->assertTrue($restored->getItem('form')->get('state')->isEmpty());
    $this->assertSame('Unsaved label', $restored->getItem('consumer')->get('title')->value);
    $this->expectException(\DomainException::class);
    $editor->operate($second, 'start', ['revision' => 0]);
  }

  /**
   * Updates are private until Finish; outcomes feed subsequent checklist work.
   */
  public function testSharedFormPersistsOnlyAtCompletion(): void {
    [$host, $item] = $this->formWork();
    $editor = $this->container->get('checklist_flexiform.editor');
    $this->assertSame('new', $editor->describe($item)['status']);
    $this->assertArrayNotHasKey('template', $editor->operations($item)['start']['parameters_schema']['properties']);
    $ready = $editor->operate($item, 'start', ['revision' => 0], ChecklistAttempt::ACTION_FORM);
    $this->assertSame('ready', $ready['status']);
    $updated = $editor->operate($item, 'form/update', [
      'revision' => $ready['revision'],
      'input' => ['name' => 'Edited'],
    ]);
    $this->assertSame('Edited', $editor->describe($item)['data']->name);
    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $this->assertSame('Original', $storage->loadUnchanged($host->id())->getAccountName());
    $this->assertFalse($this->reload($item)->isComplete());
    $this->assertTrue($this->reload($item)->get('outcomes')->isEmpty());
    $finished = $editor->operate($item, 'form/submit', ['revision' => $updated['revision'], 'input' => []]);
    $this->assertSame('complete', $finished['status']);
    $saved = $this->reload($item);
    $this->assertTrue($saved->isComplete());
    $this->assertTrue($saved->get('state')->isEmpty());
    $this->assertSame('Edited', $storage->loadUnchanged($host->id())->getAccountName());
    $this->assertSame($host->id(), $saved->get('outcomes')->get('person')->getValue()->id());
    $host = $storage->loadUnchanged($host->id());
    $this->container->get('checklist.processor')->process($host->work->checklist);
    $this->assertSame([['Edited', NULL]], $this->container->get('state')->get('checklist_context_test.runs'));
    $this->assertSame(ChecklistAttempt::SUCCEEDED, $this->container->get('checklist.attempt_journal')->latest($saved)->status);
  }

  /**
   * Referenced wizards retain earlier input without saving on Next.
   */
  public function testReferencedWizard(): void {
    [$host, $item] = $this->formWork(TRUE, TRUE);
    $this->assertContains('flexiform.form.profile', $item->getHandler()->calculateDependencies()['config']);
    $editor = $this->container->get('checklist_flexiform.editor');
    $ready = $editor->operate($item, 'start', ['revision' => 0]);
    $this->assertSame('ready', $ready['status'], json_encode($ready));
    $next = $editor->operate($item, 'form/next', [
      'revision' => $ready['revision'],
      'input' => ['name' => 'Wizard name'],
    ]);
    $this->assertSame(1, $next['wizard']['page']);
    $this->assertSame('Original', $this->container->get('entity_type.manager')->getStorage('user')->loadUnchanged($host->id())->getAccountName());
    $finished = $editor->operate($item, 'form/finish', [
      'revision' => $next['revision'],
      'input' => ['language' => 'en'],
    ]);
    $this->assertSame('complete', $finished['status']);
    $saved = $this->container->get('entity_type.manager')->getStorage('user')->loadUnchanged($host->id());
    $this->assertSame('Wizard name', $saved->getAccountName());
    $this->assertSame('en', $saved->language()->getId());
  }

  /**
   * Old revisions and malformed input cannot overwrite accepted working data.
   */
  public function testInvalidAndStaleInput(): void {
    [, $item] = $this->formWork();
    $editor = $this->container->get('checklist_flexiform.editor');
    $ready = $editor->operate($item, 'start', ['revision' => 0]);
    try {
      $editor->operate($item, 'form/update', ['revision' => $ready['revision'], 'input' => ['unknown' => 'hidden']]);
      $this->fail('Unexpected properties must not be accepted.');
    }
    catch (InvalidInputException) {
      $this->assertSame($ready['revision'], $editor->describe($item)['revision']);
    }
    $editor->operate($item, 'form/update', ['revision' => $ready['revision'], 'input' => ['name' => 'Accepted']]);
    $this->expectException(ChecklistAttemptConflictException::class);
    $editor->operate($item, 'form/submit', ['revision' => $ready['revision'], 'input' => ['name' => 'Stale']]);
  }

  /**
   * Preparation resumes; failed saves retain the last accepted input.
   */
  public function testPendingPreparationAndFailedSave(): void {
    [$host, $item] = $this->formWork();
    $configuration = $host->work->configuration;
    $configuration['default_items']['form']['handler_configuration'] = [
      'form' => [
        'plugin' => 'standard',
        'configuration' => [
          'data' => ['value' => ['plugin' => 'checklist_pending_data', 'save_on_submit' => TRUE]],
          'components' => [
            'value' => [
              'component_type' => 'typed_data',
              'context' => 'value',
              'path' => '',
              'label' => 'Value',
            ],
          ],
        ],
      ],
      'outcomes' => ['value' => 'value'],
    ];
    $host->work->configuration = $configuration;
    $host->save();
    $item->set('handler',
      [
        'id' => 'flexiform',
        'configuration' => $configuration['default_items']['form']['handler_configuration'],
      ]);
    $item->get('handler')->first()->get('plugin')->setValue(NULL, FALSE);
    $item->save();
    $editor = $this->container->get('checklist_flexiform.editor');
    $pending = $editor->operate($item, 'start', ['revision' => 0]);
    $this->assertSame('preparing', $pending['status']);
    $this->assertSame('preparing', $editor->describe($item)['status']);
    $ready = $editor->operate($item, 'advance', ['revision' => $pending['revision']]);
    $this->assertSame('Prepared', $ready['data']->value);
    $updated = $editor->operate($item,
      'form/update',
      [
        'revision' => $ready['revision'],
        'input' => ['value' => 'Keep this input'],
      ]);
    Pending::$fail = TRUE;
    try {
      $editor->operate($item, 'form/submit', ['revision' => $updated['revision'], 'input' => []]);
      $this->fail('Provider save must fail.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('Test persistence failure.', $exception->getMessage());
    }
    finally {
      Pending::$fail = FALSE;
    }
    $saved = $this->reload($item);
    $this->assertFalse($saved->isComplete());
    $this->assertTrue($saved->get('outcomes')->isEmpty());
    $this->assertSame(ChecklistAttempt::FAILED, $this->container->get('checklist.attempt_journal')->latest($saved)->status);
    $this->assertSame('Keep this input', $saved->getHandler()->getEditorSession()[0]->describe()['data']->value);
  }

  /**
   * A second user cannot read another user's retained editor values.
   */
  public function testOwnerIsolation(): void {
    [$host, $item] = $this->formWork();
    $editor = $this->container->get('checklist_flexiform.editor');
    $editor->operate($item, 'start', ['revision' => 0]);
    $this->container->get('current_user')->setAccount($host);
    $this->expectException(AccessDeniedHttpException::class);
    $editor->describe($item);
  }

}
