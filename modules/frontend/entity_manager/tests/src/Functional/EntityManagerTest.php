<?php

namespace Drupal\Tests\entity_manager\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;

/**
 * Exercises the generic reader using nodes, independently of consuming apps.
 */
#[Group('entity_manager')]
final class EntityManagerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'entity_manager'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Configured View and entity access both protect the AJAX renderer.
   */
  public function testReaderAccessAndPresentation(): void {
    $this->drupalCreateContentType(['type' => 'article']);
    $visible = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Public article',
      'body' => ['value' => 'Full reader body.'],
      'status' => 1,
    ]);
    $hidden = $this->drupalCreateNode(['type' => 'article', 'title' => 'Private article', 'status' => 0]);
    $view = View::create([
      'id' => 'reader_test',
      'label' => 'Reader fixture',
      'status' => TRUE,
      'base_table' => 'node_field_data',
      'base_field' => 'nid',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'display_options' => [
            'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
            'style' => ['type' => 'entity_manager', 'options' => ['view_mode' => 'full']],
            'row' => ['type' => 'fields'],
            'fields' => [
              'title' => [
                'id' => 'title',
                'table' => 'node_field_data',
                'field' => 'title',
                'plugin_id' => 'field',
                'entity_type' => 'node',
                'entity_field' => 'title',
                'type' => 'string',
                'settings' => ['link_to_entity' => TRUE],
              ],
            ],
            'filters' => [
              'status' => [
                'id' => 'status',
                'table' => 'node_field_data',
                'field' => 'status',
                'plugin_id' => 'boolean',
                'value' => 1,
              ],
            ],
          ],
        ],
        'page' => ['id' => 'page', 'display_plugin' => 'page', 'display_options' => ['path' => 'reader-fixture']],
      ],
    ]);
    $view->save();
    $this->container->get('router.builder')->rebuild();
    $account = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($account);
    $this->drupalGet('/reader-fixture');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '[data-entity-manager]');
    $this->assertSession()->elementExists('css', 'tr[data-entity-manager-url]');
    $this->assertSession()->linkExists('Public article');
    $this->assertSession()->linkNotExists('Private article');
    $endpoint = '/entity-manager/reader_test/page/';
    $this->drupalGet($endpoint . $visible->id(), ['query' => ['entity_type' => 'user', 'view_mode' => 'arbitrary']]);
    $this->assertSession()->statusCodeEquals(200);
    $commands = json_decode($this->getSession()->getPage()->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
    $inserts = array_values(array_filter($commands, fn(array $command) => $command['command'] === 'insert'));
    $this->assertCount(1, $inserts);
    $this->assertNull($inserts[0]['selector']);
    $this->assertStringContainsString('Public article', $inserts[0]['data']);
    $this->assertStringContainsString('Full reader body.', $inserts[0]['data']);
    $this->assertSession()->responseHeaderContains('Cache-Control', 'no-store');
    $this->drupalGet($endpoint . $hidden->id());
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('/entity-manager/reader_test/unknown/' . $visible->id());
    $this->assertSession()->statusCodeEquals(404);
    $displays = $view->get('display');
    $displays['default']['display_options']['access']['options']['perm'] = 'administer nodes';
    $view->set('display', $displays)->save();
    $this->drupalGet($endpoint . $visible->id());
    $this->assertSession()->statusCodeEquals(403);
    $displays['default']['display_options']['style'] = ['type' => 'table'];
    $view->set('display', $displays)->save();
    $this->drupalGet($endpoint . $visible->id());
    $this->assertSession()->statusCodeEquals(404);
  }

}
