<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Controller\ChecklistHistoryController;
use Drupal\checklist_state_test\Plugin\ChecklistItemHandler\Iteration;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tests read-only history rendering, navigation and item access.
 *
 * @group checklist
 */
class ChecklistHistoryPageTest extends ChecklistItemExecutionTestBase {

  /**
   * History is escaped, bounded, pinned and does not execute work.
   */
  public function testRenderingAndPagination(): void {
    [$host, $item, $attempt] = $this->work();
    $journal = $this->container->get('checklist.attempt_journal');
    for ($i = 0; $i < 14; $i++) {
      $attempt = $journal->transition($attempt, ChecklistAttempt::RUNNING, 1);
      $attempt = $journal->transition($attempt, ChecklistAttempt::WAITING, 1, '<script>private()</script>');
    }
    $controller = ChecklistHistoryController::create($this->container);
    $page = $controller->view(Request::create('/'), 'user', $host->id(), 'work', 'worker');
    $this->assertCount(25, $page['events']['table']['#rows']);
    $this->assertSame(0, $page['#cache']['max-age']);
    $query = $page['navigation']['next']['#url']->getOption('query');
    $this->assertSame(['attempt' => $attempt->id, 'after_version' => 25], $query);
    $this->assertSame([], $page['navigation']['next']['#attributes']);
    $html = (string) $this->container->get('renderer')->renderRoot($page);
    $this->assertStringNotContainsString('<script>private()', $html);
    $this->assertStringContainsString('&lt;script&gt;private()', $html);
    $second = $controller->view(Request::create('/', 'GET', $query), 'user', $host->id(), 'work', 'worker');
    $this->assertCount(4, $second['events']['table']['#rows']);
    $this->assertArrayNotHasKey('next', $second['navigation']);
    $this->assertArrayHasKey('start', $second['navigation']);
    $this->assertSame($attempt->version, $journal->latest($item)->version);
    $this->assertSame([], Iteration::$calls);
    $attempt = $journal->transition($attempt, ChecklistAttempt::FAILED, 1);
    $successor = $journal->create($item, 1, 1, ChecklistAttempt::ACTION, NULL, ChecklistAttempt::RESUME, $attempt->id);
    $page = $controller->view(Request::create('/'), 'user', $host->id(), 'work', 'worker');
    $this->assertSame(['attempt' => $attempt->id], $page['navigation']['previous']['#url']->getOption('query'));
    $this->assertSame(1, $journal->latest($item)->version);
    $this->assertSame($successor->id, $journal->latest($item)->id);
  }

  /**
   * AJAX history opens one resource while retaining the rest of the workspace.
   */
  public function testHistoryResource(): void {
    [$host] = $this->work();
    $request = Request::create('/', 'GET', ['_wrapper_format' => 'drupal_ajax']);
    $response = ChecklistHistoryController::create($this->container)->view($request, 'user', $host->id(), 'work', 'worker');
    $commands = $response->getCommands();
    $this->assertCount(1, $commands);
    $this->assertSame('checklistOpenResource', $commands[0]['command']);
    $this->assertStringContainsString('data-checklist-history', $commands[0]['data']);
    $this->assertStringContainsString('history:worker', $commands[0]['data']);
    $this->assertSame([], Iteration::$calls);
  }

  /**
   * Completion dates remain useful even without recorded attempts.
   */
  public function testNoAttemptAndHistoryLink(): void {
    [$host, $item] = $this->work(record_attempt: FALSE);
    $item->setComplete()->save();
    $controller = ChecklistHistoryController::create($this->container);
    $page = $controller->view(Request::create('/'), 'user', $host->id(), 'work', 'worker');
    $this->assertArrayHasKey('empty', $page);
    $this->assertStringContainsString('Completed', (string) $page['completion']['#value']);
    $row = $this->container->get('checklist.row_builder')->build($host->work->checklist, $item);
    $this->assertArrayNotHasKey('data-dialog-renderer', $row['history']['#attributes']);
    $this->assertSame('checklist.item.history_page', $row['history']['#url']->getRouteName());
  }

  /**
   * Field access is enforced by the same reader as the API.
   */
  public function testDeniedHistory(): void {
    [$host] = $this->work();
    $this->container->get('state')->set('checklist_resolver_test.denied_field_operations', ['work' => ['view']]);
    $this->expectException(AccessDeniedHttpException::class);
    ChecklistHistoryController::create($this->container)->view(Request::create('/'), 'user', $host->id(), 'work', 'worker');
  }

  /**
   * Attempts from another item cannot be opened through this page.
   */
  public function testForeignAttempt(): void {
    [$host] = $this->work();
    [, , $other] = $this->work(name: 'Other');
    $this->expectException(NotFoundHttpException::class);
    ChecklistHistoryController::create($this->container)->view(Request::create('/', 'GET', ['attempt' => $other->id]), 'user', $host->id(), 'work', 'worker');
  }

  /**
   * Actor names are not exposed when the viewer cannot view their profiles.
   */
  public function testPrivateActorName(): void {
    [$host, $item] = $this->work(record_attempt: FALSE);
    $this->container->get('checklist.attempt_journal')->create($item, 1, 1, ChecklistAttempt::ACTION);
    $this->container->get('current_user')->setAccount($host);
    $page = ChecklistHistoryController::create($this->container)->view(Request::create('/'), 'user', $host->id(), 'work', 'worker');
    $this->assertStringContainsString('User 1', (string) $page['summary']['#value']);
    $this->assertStringNotContainsString('Caller', (string) $page['summary']['#value']);
  }

}
