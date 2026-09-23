<?php

namespace Drupal\Tests\checklist_entity_template\Functional;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Exercises real browser submissions against the shared operation session.
 *
 * @group checklist_entity_template
 */
class SharedEditorTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['checklist_entity_template', 'checklist_template_test', 'entity_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Creates a saved template item with an embedded standard or wizard editor.
   */
  protected function work(bool $pending = FALSE, bool $choice = FALSE): array {
    $account = $this->drupalCreateUser([], 'Editor', TRUE);
    $this->drupalLogin($account);
    FieldStorageConfig::create(['field_name' => 'work', 'entity_type' => 'entity_test', 'type' => 'checklist'])->save();
    FieldConfig::create([
      'field_name' => 'work',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'translatable' => FALSE,
    ])->save();
    $host = EntityTest::create([
      'name' => 'Host',
      'work' => [
        'id' => 'template_test',
        'configuration' => [
          'default_items' => [
            'create' => [
              'title' => 'Create record',
              'handler' => 'entity_template__create',
              'handler_configuration' => [
                'template' => [
                  'id' => 'standalone',
                  'target_entity_type_id' => 'entity_test',
                  'target_entity_bundle' => 'entity_test',
                  'components' => [
                    'name' => ['id' => 'property_value', 'path' => 'name.0.value', 'value' => 'Prepared name'],
                  ],
                ],
                'editor' => [
                  'plugin' => 'standard',
                  'configuration' => [
                    'data' => ['entity' => ['plugin' => 'provided_data']],
                    'components' => [
                      'name' => [
                        'component_type' => 'typed_data',
                        'context' => 'entity',
                        'path' => 'name.0.value',
                        'label' => 'Name',
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ]);
    if ($pending) {
      $configuration = $host->work->configuration;
      $handler = &$configuration['default_items']['create']['handler_configuration'];
      $handler['template']['parameters'] = ['title' => ['type' => 'string', 'required' => TRUE]];
      $handler['template']['components']['name']['id'] = 'checklist_pending';
      $handler['context_mapping'] = ['title' => 'checklist:entity.name.value'];
      $handler['editor']['plugin'] = 'wizard';
      $handler['editor']['configuration']['components']['language'] = [
        'component_type' => 'typed_data',
        'context' => 'entity',
        'path' => 'langcode.0.value',
        'label' => 'Language',
      ];
      $handler['editor']['configuration']['pages'] = [
        ['label' => 'Name', 'components' => ['name']],
        ['label' => 'Language', 'components' => ['language']],
      ];
      unset($handler);
      $host->work->configuration = $configuration;
    }
    $configuration = $host->work->configuration;
    $settings = $configuration['default_items']['create']['handler_configuration'];
    $configuration['default_items']['create']['handler_configuration'] = [
      'templates' => [
        'default' => [
          'template' => ['type' => 'embedded', 'configuration' => $settings['template']],
          'context_mapping' => $settings['context_mapping'] ?? [],
          'editor' => $settings['editor'],
        ],
      ],
    ];
    if ($choice) {
      $candidate = $configuration['default_items']['create']['handler_configuration']['templates']['default'];
      $candidate['template']['configuration']['label'] = 'Second template';
      $candidate['template']['configuration']['components']['name']['value'] = 'Second prepared name';
      $configuration['default_items']['create']['handler_configuration']['templates']['second'] = $candidate;
    }
    $host->work->configuration = $configuration;
    $host->save();
    $item = $host->work->checklist->getItem('create');
    $item->save();
    $path = '/checklist/entity_test/' . $host->id() . '/work/create/action';
    return [$item, $path, $account];
  }

  /**
   * Browser and API callers share working values and reject stale submissions.
   */
  public function testBrowserHandoff(): void {
    [$item, $path, $account] = $this->work();
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('Preparing your form');
    $this->submitForm([], 'Open form');
    $this->assertSession()->fieldValueEquals('editor[values][name]', 'Prepared name');
    $this->assertSession()->pageTextNotContains('Preparing your form');

    // Run an API operation while the rendered browser form stays open.
    $this->container->get('current_user')->setAccount($account);
    $editor = $this->container->get('checklist_entity_template.editor');
    $this->assertSame('action_form', $this->container->get('checklist.attempt_journal')->latest($item)->path);
    $description = $editor->describe($item);
    $editor->operate($item, 'form/update', [
      'revision' => $description['revision'],
      'input' => ['name' => 'API changed'],
    ]);
    $this->submitForm(['editor[values][name]' => 'Stale browser'], 'Submit');
    $this->assertSession()->pageTextContains('The editor changed. Refresh before submitting again.');
    $this->assertCount(1, EntityTest::loadMultiple());

    $this->drupalGet($path);
    $this->assertSession()->fieldValueEquals('editor[values][name]', 'API changed');
    $this->submitForm(['editor[values][name]' => 'Browser changed'], 'Update');
    $this->assertSession()->fieldValueEquals('editor[values][name]', 'Browser changed');
    $this->assertSame('Browser changed', $editor->describe($item)['data']->name);
    $this->submitForm([], 'Submit');
    $this->assertSession()->pageTextContains('Entity created.');
    $this->assertSame('complete', $editor->describe($item)['status']);
    $this->assertCount(2, EntityTest::loadMultiple());
  }

  /**
   * Pending preparation auto-polls, and wizard navigation saves only on Finish.
   */
  public function testPreparingWizard(): void {
    [, $path] = $this->work(TRUE);
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '[data-flexiform-advance="new"]');
    $this->assertSession()->pageTextNotContains('Preparing your form');
    $this->submitForm([], 'Open form');
    $this->assertSession()->pageTextContains('Preparing your form');
    $this->assertSession()->elementExists('css', '[data-flexiform-advance="preparing"]');
    $this->assertSession()->fieldNotExists('editor[values][name]');
    $this->submitForm([], 'Check progress');
    $this->assertSession()->fieldValueEquals('editor[values][name]', 'Host');
    $this->submitForm(['editor[values][name]' => 'Wizard browser'], 'Next');
    $this->assertSession()->fieldExists('editor[values][language]');
    $this->assertCount(1, EntityTest::loadMultiple());
    $this->submitForm([], 'Previous');
    $this->assertSession()->fieldValueEquals('editor[values][name]', 'Wizard browser');
    $this->submitForm([], 'Next');
    $this->submitForm([], 'Finish');
    $this->assertSession()->pageTextContains('Entity created.');
    $this->assertCount(2, EntityTest::loadMultiple());
  }

  /**
   * Several matching templates present a choice before preparing an entity.
   */
  public function testTemplateChoice(): void {
    [, $path] = $this->work(choice: TRUE);
    $this->drupalGet($path);
    $this->assertSession()->fieldExists('template');
    $this->assertSession()->elementNotExists('css', '[data-flexiform-advance="new"]');
    $this->assertCount(1, EntityTest::loadMultiple());
    $this->submitForm(['template' => 'second'], 'Select template');
    $this->assertSession()->fieldValueEquals('editor[values][name]', 'Second prepared name');
    $this->submitForm(['editor[values][name]' => 'Selected in browser'], 'Submit');
    $this->assertSession()->pageTextContains('Entity created.');
    $this->assertCount(2, EntityTest::loadMultiple());
  }

}
