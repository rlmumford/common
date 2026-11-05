<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist_state_test\Plugin\ChecklistItemHandler\Iteration;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use Drupal\user\Entity\User;

/**
 * Tests display-only reports, explicit previews and bounded history.
 *
 * @group checklist
 */
final class ChecklistReportBuilderTest extends ChecklistItemExecutionTestBase {

  /**
   * Reports cannot execute work or expose arbitrary outcome contents.
   */
  public function testExplicitPreviewAndNoWrites(): void {
    [$host, $item, $attempt] = $this->work(['throw' => TRUE]);
    $item->setOutcome('result', '<script>private payload</script>')->save();
    $before = $this->reload($item)->toArray();
    $journal = $this->container->get('checklist.attempt_journal');
    $events = $journal->history($attempt->id);
    $builder = $this->container->get('checklist.report_builder');
    $build = $builder->build($host, 'work');
    $this->assertSame('container', $build['#type']);
    $this->assertSame(0, $build['#cache']['max-age']);
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('Worker', $html);
    $this->assertStringContainsString('Recorded', $html);
    $this->assertStringContainsString('Queued', $html);
    $this->assertStringNotContainsString('private payload', $html);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertStringNotContainsString('<input', $html);
    $build = $builder->build($host, 'work', 0, static fn($item, $key) => ['#plain_text' => $item->get('outcomes')->get($key)->getValue()]);
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('&lt;script&gt;private payload&lt;/script&gt;', $html);
    $this->assertSame([], Iteration::$calls);
    $this->assertSame($before, $this->reload($item)->toArray());
    $this->assertEquals($attempt, $journal->latest($item));
    $this->assertSame($events, $journal->history($attempt->id));
  }

  /**
   * Hidden fields and items never invoke the application preview callback.
   */
  public function testVisibilityBeforePreview(): void {
    [$host, $item] = $this->work();
    $item->setOutcome('result', 'Private evidence')->save();
    $builder = $this->container->get('checklist.report_builder');
    $preview = static function () {
      throw new \LogicException('Inaccessible evidence must not be previewed.');
    };
    $this->container->get('state')->set('checklist_resolver_test.denied_field_operations', ['outcomes' => ['view']]);
    $build = $builder->build($host, 'work', 0, $preview);
    $this->assertArrayNotHasKey('outcomes', $build['items']['worker']);
    $this->container->get('state')->set('checklist_resolver_test.denied_field_operations', ['work' => ['view']]);
    $this->expectException(AccessDeniedHttpException::class);
    $builder->build($host, 'work', 0, $preview);
  }

  /**
   * Long histories use pinned cursors without fetching other item attempts.
   */
  public function testBoundedHistory(): void {
    [$host, , $attempt] = $this->work();
    $journal = $this->container->get('checklist.attempt_journal');
    for ($i = 0; $i < 54; $i++) {
      $attempt = $journal->transition($attempt, $i % 2 ? ChecklistAttempt::WAITING : ChecklistAttempt::RUNNING, 1, 'Event ' . $i);
    }
    $builder = $this->container->get('checklist.report_builder');
    $build = $builder->build($host, 'work');
    $history = $build['items']['worker']['history'];
    $this->assertCount(50, $history['events']['#rows']);
    $query = $history['navigation']['later']['#url']->getOption('query');
    $this->assertSame($attempt->id, $query['checklist_history']['work'][0]['worker']['attempt']);
    $request = Request::create('/report', 'GET', $query);
    $request->setSession($this->container->get('request_stack')->getCurrentRequest()->getSession());
    $this->container->get('request_stack')->push($request);
    try {
      $next = $builder->build($host, 'work')['items']['worker']['history'];
      $this->assertTrue($next['#open']);
      $this->assertCount(5, $next['events']['#rows']);
      $this->assertArrayNotHasKey('later', $next['navigation']);
      $this->assertArrayHasKey('first', $next['navigation']);
      $this->assertArrayHasKey('latest', $next['navigation']);
    }
    finally {
      $this->container->get('request_stack')->pop();
    }
    $this->assertSame([], Iteration::$calls);
  }

  /**
   * Malformed query structures are rejected rather than cast or indexed.
   */
  public function testMalformedCursor(): void {
    [$host] = $this->work();
    $stack = $this->container->get('request_stack');
    $stack->push(Request::create('/report', 'GET', ['checklist_history' => 'invalid']));
    try {
      $this->expectException(BadRequestHttpException::class);
      $this->container->get('checklist.report_builder')->build($host, 'work');
    }
    finally {
      $stack->pop();
    }
  }

  /**
   * The shared reader rejects an attempt belonging to another checklist item.
   */
  public function testForeignAttempt(): void {
    [$host] = $this->work();
    [, , $other] = $this->work(name: 'Other host');
    $stack = $this->container->get('request_stack');
    $stack->push(Request::create('/report', 'GET', [
      'checklist_history' => ['work' => [0 => ['worker' => ['attempt' => $other->id]]]],
    ]));
    try {
      $this->expectException(NotFoundHttpException::class);
      $this->container->get('checklist.report_builder')->build($host, 'work');
    }
    finally {
      $stack->pop();
    }
  }

  /**
   * Hidden item titles, evidence and history are omitted entirely.
   */
  public function testHiddenItem(): void {
    $this->enableModules(['checklist_reader_test']);
    $host = User::create([
      'name' => 'Hidden host',
      'work' => [
        'id' => 'context_test',
        'configuration' => [
          'default_items' => [
            'hidden' => ['title' => 'Private item title', 'handler' => 'iteration_test', 'handler_configuration' => []],
          ],
        ],
      ],
    ]);
    $host->save();
    $build = $this->container->get('checklist.report_builder')->build($host, 'work', 0, static function () {
      throw new \LogicException('Hidden evidence must not be previewed.');
    });
    $this->assertArrayNotHasKey('hidden', $build['items']);
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);
    $this->assertStringNotContainsString('Private item title', $html);
    $this->assertStringContainsString('No visible checklist items.', $html);
  }

}
