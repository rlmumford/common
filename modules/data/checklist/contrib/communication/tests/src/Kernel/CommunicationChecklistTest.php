<?php

namespace Drupal\Tests\checklist_communication\Kernel;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist_communication_test\Plugin\Communication\Operation\Recorded;
use Drupal\communication\Entity\Communication;
use Drupal\communication\Entity\CommunicationParticipant;
use Drupal\entity_template\Entity\TemplateBuilder;
use Drupal\entity_template\Entity\TemplateBlueprint;
use Drupal\Tests\checklist\Kernel\ChecklistItemExecutionTestBase;
use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\user\Entity\User;

/**
 * Tests atomic template/editor handoff and audited communication operations.
 *
 * @group checklist_communication
 */
class CommunicationChecklistTest extends ChecklistItemExecutionTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ctools', 'token', 'flexiform', 'entity_template', 'checklist_flexiform',
    'checklist_entity_template', 'entity_template_flexiform', 'communication', 'telephone', 'datetime', 'file', 'views',
    'layout_discovery', 'layout_builder', 'contextual', 'block',
    'checklist_communication', 'checklist_communication_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('communication');
    $this->installEntitySchema('communication_participant');
    $this->installEntitySchema('communication_event');
    Recorded::$calls = [];
    Recorded::$fail = FALSE;
    Recorded::$interrupt = FALSE;
  }

  /**
   * Returns one candidate with its own editor and follow-up operation settings.
   */
  protected function candidate(string $subject, bool $edit = FALSE, bool $confirm = FALSE): array {
    $candidate = [
      'template' => [
        'type' => 'embedded',
        'configuration' => [
          'id' => 'standalone',
          'target_entity_type_id' => 'communication',
          'target_entity_bundle' => 'email',
          'components' => [
            'subject' => ['id' => 'property_value', 'path' => 'subject.0.value', 'value' => $subject],
            'status' => ['id' => 'property_value', 'path' => 'status.0.value', 'value' => 'draft'],
          ],
        ],
      ],
      'operation' => ['id' => 'recorded', 'variant' => '', 'confirm' => $confirm, 'label' => 'Send ' . $subject],
    ];
    if ($edit) {
      $candidate['editor'] = [
        'plugin' => 'standard',
        'configuration' => [
          'data' => [
            'entity' => [
              'plugin' => 'provided_data',
              'data_type' => 'entity:communication',
              'save_on_submit' => FALSE,
            ],
          ],
          'components' => [
            'subject' => [
              'component_type' => 'typed_data',
              'context' => 'entity',
              'path' => 'subject.0.value',
              'label' => 'Subject',
            ],
          ],
        ],
      ];
    }
    return $candidate;
  }

  /**
   * Creates one configured parent, not three separate authored items.
   */
  protected function communicationWork(array $templates): array {
    $host = User::create([
      'name' => 'Work owner',
      'status' => 1,
      'work' => [
        'id' => 'context_test',
        'configuration' => [
          'default_items' => [
            'prepare' => [
              'title' => 'Prepare communication',
              'handler' => 'entity_template__create_communication',
              'handler_configuration' => ['templates' => $templates],
            ],
          ],
        ],
      ],
    ]);
    $host->save();
    $item = $host->work->checklist->getItem('prepare');
    $item->save();
    $this->assertConfigSchema($this->container->get('config.typed'), 'checklist_item_handler.entity_template__create_communication', $item->getHandler()->getConfiguration());
    return [$host, $item];
  }

  /**
   * Reads saved work without consulting legacy HTML tempstore.
   */
  protected function checklist($host) {
    $host = $this->container->get('entity_type.manager')->getStorage('user')->loadUnchanged($host->id());
    return $this->container->get('checklist.resolver')->resolve($host, 'work', 0, 'update');
  }

  /**
   * Saving creates required follow-up work without duplicate submissions.
   */
  public function testAutomaticHandoff(): void {
    [$host, $parent] = $this->communicationWork(['default' => $this->candidate('Automatic')]);
    $tempstore = $this->container->get('checklist.tempstore_repository');
    $tempstore->set($this->checklist($host));
    $executor = $this->container->get('checklist.item_executor');
    $this->assertSame(ChecklistAttempt::SUCCEEDED, $executor->submit($parent)->status);
    $checklist = $this->checklist($host);
    $this->assertCount(2, $checklist->getItems());
    $this->assertCount(2, $tempstore->get($checklist)->getItems());
    $this->assertCount(1, Communication::loadMultiple());
    $this->assertFalse($checklist->isCompletable());
    $child = array_values($checklist->getItems())[1];
    $this->assertTrue($child->isRequired());
    $this->assertSame('recorded', $child->getHandler()->getConfiguration()['operation']);
    $this->assertSame(ChecklistAttempt::SUCCEEDED, $executor->submit($child)->status);
    $this->assertCount(1, Recorded::$calls);
    $this->assertTrue($this->checklist($host)->isCompletable());
    $this->assertTrue((bool) $this->reload($child)->get('outcomes')->get('succeeded')->getValue());
    $this->assertSame('sent', $this->reload($child)->get('outcomes')->get('communication')->getValue()->get('status')->value);
    $executor->submit($parent);
    $executor->submit($child);
    $this->assertCount(2, $this->checklist($host)->getItems());
    $this->assertCount(1, Recorded::$calls);
  }

  /**
   * Saved messages share a read-only resource before and after delivery.
   */
  public function testCommunicationResource(): void {
    [$host, $parent] = $this->communicationWork(['default' => $this->candidate('Review this message')]);
    $collector = $this->container->get('checklist.action_resource_collector');
    $this->assertSame([], $collector->collect($this->checklist($host)));
    $executor = $this->container->get('checklist.item_executor');
    $executor->submit($parent);
    $checklist = $this->checklist($host);
    $resources = $collector->collect($checklist);
    $this->assertCount(1, $resources);
    $entry = reset($resources);
    $this->assertCount(2, $entry['owners']);
    $communications = Communication::loadMultiple();
    $communication = reset($communications);
    $this->assertSame('communication:' . $communication->uuid(), $entry['resource']->getKey());
    $content = $entry['resource']->getContent();
    $html = (string) $this->container->get('renderer')->renderRoot($content);
    $this->assertStringContainsString('Review this message', $html);
    $this->assertSame([], Recorded::$calls);
    $child = array_values($checklist->getItems())[1];
    $executor->submit($child);
    $resources = $collector->collect($this->checklist($host));
    $this->assertCount(1, $resources);
    $this->assertCount(2, reset($resources)['owners']);
    $resource = $this->container->get('checklist_communication.resource')->build($communication);
    $content = $resource->getContent();
    $this->assertSame('sent', $content['message']['#communication']->get('status')->value);
    $this->assertCount(1, Recorded::$calls);
    $this->container->get('state')->set('checklist_communication_test.deny', TRUE);
    $this->container->get('entity_type.manager')->getAccessControlHandler('communication')->resetCache();
    $this->assertSame([], $collector->collect($this->checklist($host)));
  }

  /**
   * Review respects field access and never publishes unsaved working values.
   */
  public function testResourceFieldAccess(): void {
    $builder = $this->container->get('checklist_communication.resource');
    $communication = Communication::create([
      'mode' => 'email',
      'subject' => 'Private subject',
      'body_plain' => 'Private message',
    ]);
    $this->assertNull($builder->build($communication));
    $communication->save();
    $communication->set('subject', 'Unsaved working subject');
    $content = $builder->build($communication)->getContent();
    $html = (string) $this->container->get('renderer')->renderRoot($content);
    $this->assertStringContainsString('Private subject', $html);
    $this->assertStringContainsString('Private message', $html);
    $this->assertStringNotContainsString('Unsaved working subject', $html);
    $this->container->get('state')->set('checklist_communication_test.hide_content', TRUE);
    $this->container->get('entity_type.manager')->getAccessControlHandler('communication')->resetCache();
    $this->container->get('cache.render')->deleteAll();
    $content = $builder->build($communication)->getContent();
    $html = (string) $this->container->get('renderer')->renderRoot($content);
    $this->assertStringNotContainsString('Private subject', $html);
    $this->assertStringNotContainsString('Private message', $html);
    $communication->delete();
    $this->assertNull($builder->build($communication));
    $this->assertSame([], Recorded::$calls);
  }

  /**
   * Creates a real Drupal-mail follow-up with optional recipients.
   */
  protected function drupalMailWork(bool $recipient = TRUE): array {
    $this->config('system.site')->set('mail', 'site@example.com')->save();
    $this->config('system.mail')->set('interface.default', 'test_mail_collector')->save();
    $this->config('communication.mode.email')->set('enabled_op_variants.send', ['send_email_mailsystem'])->save();
    $candidate = $this->candidate('Welcome Alex', FALSE, TRUE);
    $candidate['operation']['id'] = 'send';
    $candidate['operation']['variant'] = 'send_email_mailsystem';
    $candidate['template']['configuration']['components']['body'] = [
      'id' => 'property_value',
      'path' => 'body_plain.0.value',
      'value' => 'Your appointment is Tuesday.',
    ];
    [$host, $parent] = $this->communicationWork(['default' => $candidate]);
    $executor = $this->container->get('checklist.item_executor');
    $this->assertSame(ChecklistAttempt::SUCCEEDED, $executor->submit($parent)->status);
    $communication = $this->reload($parent)->get('outcomes')->get('entity')->getValue();
    if ($recipient) {
      foreach (['to' => 'alex@example.com', 'cc' => 'coordinator@example.com'] as $role => $email) {
        CommunicationParticipant::create([
          'type' => 'email',
          'communication' => $communication,
          'role' => $role,
          'email' => $email,
        ])->save();
      }
    }
    $child = array_values($this->checklist($host)->getItems())[1];
    return [$host, $child, $communication];
  }

  /**
   * The real send operation publishes mail, outcomes and its sent event once.
   */
  public function testDrupalMailHandoff(): void {
    [$host, $child, $communication] = $this->drupalMailWork();
    $executor = $this->container->get('checklist.item_executor');
    $attempt = $executor->submit($child);
    $this->assertSame(ChecklistAttempt::WAITING, $attempt->status);
    $this->assertSame([], $this->container->get('state')->get('system.test_mail_collector', []));
    $child = $this->checklist($host)->getItem($child->getName());
    $this->container->get('checklist.context_preparer')->prepare($this->checklist($host), $child);
    $child->getHandler()->confirm($attempt->id, $attempt->version);
    $queued = $this->container->get('checklist.attempt_journal')->latest($child);
    $this->assertSame(ChecklistAttempt::SUCCEEDED, $executor->run($queued)->status);
    $messages = $this->container->get('state')->get('system.test_mail_collector', []);
    $this->assertCount(1, $messages);
    $this->assertSame('alex@example.com', $messages[0]['to']);
    $this->assertSame('coordinator@example.com', $messages[0]['headers']['Cc']);
    $this->assertSame('Welcome Alex', $messages[0]['subject']);
    $this->assertStringContainsString('Your appointment is Tuesday.', $messages[0]['body']);
    $this->assertSame('sent', Communication::load($communication->id())->get('status')->value);
    $events = $this->container->get('entity_type.manager')->getStorage('communication_event');
    $this->assertCount(1, $events->loadByProperties(['communication' => $communication->id(), 'type' => 'sent']));
    $this->assertTrue($this->checklist($host)->isCompletable());
    $executor->submit($child);
    $this->assertCount(1, $this->container->get('state')->get('system.test_mail_collector', []));
  }

  /**
   * A recipient changed after confirmation is revalidated by the worker.
   */
  public function testDrupalMailRevalidatesRecipient(): void {
    [$host, $child, $communication] = $this->drupalMailWork();
    $executor = $this->container->get('checklist.item_executor');
    $attempt = $executor->submit($child);
    $checklist = $this->checklist($host);
    $child = $checklist->getItem($child->getName());
    $this->container->get('checklist.context_preparer')->prepare($checklist, $child);
    $child->getHandler()->confirm($attempt->id, $attempt->version);
    $participants = $this->container->get('entity_type.manager')->getStorage('communication_participant');
    foreach ($participants->loadByProperties(['communication' => $communication->id(), 'role' => 'to']) as $participant) {
      $participant->set('email', 'not-an-email')->save();
    }
    $queued = $this->container->get('checklist.attempt_journal')->latest($child);
    try {
      $executor->run($queued);
      $this->fail('Changed recipients must be revalidated before delivery.');
    }
    catch (\DomainException $exception) {
      $this->assertStringContainsString('not currently valid', $exception->getMessage());
    }
    $this->assertSame(ChecklistAttempt::FAILED, $this->container->get('checklist.attempt_journal')->latest($child)->status);
    $this->assertSame([], $this->container->get('state')->get('system.test_mail_collector', []));
    $this->assertFalse($this->checklist($host)->isCompletable());
  }

  /**
   * A missing recipient fails before confirmation or transport side effects.
   */
  public function testDrupalMailMissingRecipient(): void {
    [$host, $child, $communication] = $this->drupalMailWork(FALSE);
    $executor = $this->container->get('checklist.item_executor');
    try {
      $executor->submit($child);
      $this->fail('Missing recipients must fail validation.');
    }
    catch (\DomainException $exception) {
      $this->assertStringContainsString('not currently valid', $exception->getMessage());
    }
    $this->assertSame(ChecklistAttempt::FAILED, $executor->submit($child)->status);
    $this->assertSame([], $this->container->get('state')->get('system.test_mail_collector', []));
    $this->assertSame('draft', Communication::load($communication->id())->get('status')->value);
    $this->assertFalse($this->checklist($host)->isCompletable());
  }

  /**
   * Template choice selects its editor and operation; confirmation queues once.
   */
  public function testSelectedEditorAndConfirmation(): void {
    [$host, $parent] = $this->communicationWork([
      'first' => $this->candidate('First'),
      'second' => $this->candidate('Second', TRUE, TRUE),
      'hidden' => ['condition' => ['id' => 'condition_constant:false']] + $this->candidate('Hidden'),
    ]);
    $editor = $this->container->get('checklist_entity_template.editor');
    $this->assertSame(['first', 'second'], array_keys($editor->describe($parent)['templates']));
    $ready = $editor->operate($parent, 'start', ['revision' => 0, 'template' => 'second']);
    $this->assertSame('Second', $ready['data']->subject);
    $this->assertCount(0, Communication::loadMultiple());
    $this->assertCount(1, $this->checklist($host)->getItems());
    $editor->operate($parent, 'form/submit', [
      'revision' => $ready['revision'],
      'input' => ['subject' => 'Reviewed second'],
    ]);
    $child = array_values($this->checklist($host)->getItems())[1];
    $this->assertSame('Send Second', $child->get('title')->value);
    $executor = $this->container->get('checklist.item_executor');
    $waiting = $executor->submit($child);
    $this->assertSame(ChecklistAttempt::WAITING, $waiting->status);
    $executor->run($waiting);
    $this->assertCount(0, Recorded::$calls);
    $handler = $this->reload($child)->getHandler();
    $this->container->get('checklist.context_preparer')->prepare($this->checklist($host), $handler->getItem());
    $handler->executeActionOperation('confirm', ['attempt_id' => $waiting->id, 'version' => $waiting->version]);
    $queued = $this->container->get('checklist.attempt_journal')->latest($child);
    $executor->run($queued);
    $this->assertSame('Reviewed second', Recorded::$calls[0][1]);
    $this->assertTrue($this->checklist($host)->isCompletable());
  }

  /**
   * Referenced templates inherit the builder form without a candidate override.
   */
  public function testTemplateBuilderEditor(): void {
    $candidate = $this->candidate('Inherited', TRUE, TRUE);
    $builder = TemplateBuilder::create([
      'id' => 'email',
      'label' => 'Email',
      'return_type' => 'entity:communication:email',
    ]);
    $builder->setThirdPartySetting('entity_template_flexiform', 'form', $candidate['editor']);
    $builder->save();
    $this->container->get('plugin.manager.entity_template.builder')->clearCachedDefinitions();
    TemplateBlueprint::create([
      'id' => 'email',
      'label' => 'Email',
      'builder' => 'config:email',
      'templates' => [
        'welcome' => [
          'id' => 'default',
          'label' => 'Welcome',
          'components' => $candidate['template']['configuration']['components'],
        ],
      ],
    ])->save();
    $candidate['template'] = [
      'type' => 'referenced',
      'configuration' => [
        'blueprint' => 'email',
        'template_id' => 'welcome',
      ],
    ];
    unset($candidate['editor']);
    [$host, $parent] = $this->communicationWork(['default' => $candidate]);
    $this->assertSame('interactive', $parent->getHandler()->getMethod());
    $editor = $this->container->get('checklist_entity_template.editor');
    $ready = $editor->operate($parent, 'start', ['revision' => 0]);
    $this->assertSame('Inherited', $ready['data']->subject);
    $this->assertCount(0, Communication::loadMultiple());
    $editor->operate($parent, 'form/submit', [
      'revision' => $ready['revision'],
      'input' => ['subject' => 'Reviewed inherited form'],
    ]);
    $this->assertCount(2, $this->checklist($host)->getItems());
  }

  /**
   * Editing configuration cannot change an already selected operation.
   */
  public function testSelectedOperationIsRetained(): void {
    [$host, $parent] = $this->communicationWork(['default' => $this->candidate('Pinned', TRUE, TRUE)]);
    $editor = $this->container->get('checklist_entity_template.editor');
    $ready = $editor->operate($parent, 'start', ['revision' => 0]);
    $parent = $this->reload($parent);
    $configuration = $parent->getHandler()->getConfiguration();
    $configuration['templates']['default']['operation']['confirm'] = FALSE;
    $configuration['templates']['default']['operation']['label'] = 'Changed after selection';
    $parent->get('handler')->configuration = $configuration;
    $parent->save();
    $editor->operate($parent, 'form/submit', ['revision' => $ready['revision'], 'input' => ['subject' => 'Pinned edit']]);
    $child = array_values($this->checklist($host)->getItems())[1];
    $this->assertSame('Send Pinned', $child->get('title')->value);
    $this->assertTrue($child->getHandler()->getConfiguration()['confirm']);
  }

  /**
   * Losing access blocks transport even when the item itself remains visible.
   */
  public function testOperationAccess(): void {
    [$host, $parent] = $this->communicationWork(['default' => $this->candidate('Access')]);
    $executor = $this->container->get('checklist.item_executor');
    $executor->submit($parent);
    $child = array_values($this->checklist($host)->getItems())[1];
    $this->container->get('state')->set('checklist_communication_test.deny', TRUE);
    $this->container->get('entity_type.manager')->getAccessControlHandler('communication')->resetCache();
    try {
      $executor->submit($child);
      $this->fail('Entity access must be checked before delivery.');
    }
    catch (AccessDeniedHttpException $exception) {
      $this->assertStringContainsString('not accessible', $exception->getMessage());
    }
    $this->assertCount(0, Recorded::$calls);
    $this->assertFalse($this->reload($child)->isComplete());
  }

  /**
   * Reported failure blocks resolution and is not automatically replayed.
   */
  public function testFailedDelivery(): void {
    [$host, $parent] = $this->communicationWork(['default' => $this->candidate('Failure')]);
    $executor = $this->container->get('checklist.item_executor');
    $executor->submit($parent);
    $child = array_values($this->checklist($host)->getItems())[1];
    Recorded::$fail = TRUE;
    $this->assertSame(ChecklistAttempt::FAILED, $executor->submit($child)->status);
    $this->assertFalse($this->checklist($host)->isCompletable());
    $executor->submit($child);
    $this->assertCount(1, Recorded::$calls);
    $this->assertFalse((bool) $this->reload($child)->get('outcomes')->get('succeeded')->getValue());
  }

  /**
   * A failed atomic handoff rolls back the new communication as well.
   */
  public function testHandoffRollback(): void {
    [, $parent] = $this->communicationWork(['default' => $this->candidate('Rollback')]);
    $this->container->get('entity_type.manager')->getStorage('checklist_item')->create([
      'checklist_type' => $parent->bundle(),
      'name' => 'communication__' . str_replace('-', '', $parent->uuid()),
      'checklist' => $parent->get('checklist')->getValue(),
      'handler' => ['id' => 'simply_checkable'],
    ])->save();
    try {
      $this->container->get('checklist.item_executor')->submit($parent);
      $this->fail('A conflicting child must abort the save.');
    }
    catch (\DomainException $exception) {
      $this->assertStringContainsString('already has operation work', $exception->getMessage());
    }
    $this->assertCount(0, Communication::loadMultiple());
    $this->assertFalse($this->reload($parent)->isComplete());
    $this->assertCount(0, Recorded::$calls);
  }

  /**
   * An uncertain external result is audited and never retried by redelivery.
   */
  public function testInterruptedDelivery(): void {
    [$host, $parent] = $this->communicationWork(['default' => $this->candidate('Interrupted')]);
    $executor = $this->container->get('checklist.item_executor');
    $executor->submit($parent);
    $child = array_values($this->checklist($host)->getItems())[1];
    Recorded::$interrupt = TRUE;
    try {
      $executor->submit($child);
      $this->fail('The provider interruption must propagate.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('Uncertain provider response.', $exception->getMessage());
    }
    $this->assertSame(ChecklistAttempt::FAILED, $executor->submit($child)->status);
    $this->assertCount(1, Recorded::$calls);
    $this->assertFalse($this->checklist($host)->isCompletable());
  }

}
