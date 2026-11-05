<?php

namespace Drupal\checklist;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ExpectedOutcomeChecklistItemHandlerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\ListInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Presents saved checklist evidence without forms, tempstore or execution.
 */
final class ChecklistReportBuilder {

  use StringTranslationTrait;

  /**
   * Constructs the read-only checklist presenter.
   *
   * @param \Drupal\checklist\ChecklistResolver $resolver
   *   Authorizes the host and checklist field.
   * @param \Drupal\checklist\ChecklistItemReader $reader
   *   Reads authorized item history.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dates
   *   Formats history in the viewer's timezone.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requests
   *   Supplies bounded history cursors for the current report page.
   */
  public function __construct(
    private readonly ChecklistResolver $resolver,
    private readonly ChecklistItemReader $reader,
    private readonly DateFormatterInterface $dates,
    private readonly RequestStack $requests,
  ) {}

  /**
   * Builds item rows, labeled outcomes and expandable execution history.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $host
   *   The authoritative checklist host; access is rechecked here.
   * @param string $field
   *   The checklist field name.
   * @param int $delta
   *   The checklist field delta.
   * @param callable|null $preview
   *   Optional function (ChecklistItemInterface $item, string $outcome): array.
   *   Called only for visible, recorded, declared outcomes. The application
   *   must authorize referenced entities/fields and select safe preview data.
   *   Without this callback, only evidence labels and availability are shown;
   *   arbitrary typed outcomes can contain secrets and are never dumped here.
   *
   * @return array
   *   An uncacheable read-only render array.
   */
  public function build(FieldableEntityInterface $host, string $field, int $delta = 0, ?callable $preview = NULL): array {
    $checklist = $this->resolver->resolve($host, $field, $delta);
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['checklist-report']],
      '#attached' => ['library' => ['checklist/report']],
      '#cache' => ['max-age' => 0],
      'heading' => ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Checklist')],
      'items' => ['#type' => 'container', '#attributes' => ['class' => ['checklist-report__items']]],
    ];
    foreach ($checklist->getOrderedItems() as $name => $item) {
      if (!$item->access('view action state')) {
        continue;
      }
      [$attempt, $after] = $this->cursor($field, $delta, $name);
      $history = $this->reader->readHistory($host, $field, $delta, $name, $attempt, $after, 50);
      $state = $history['status'];
      $row = [
        '#type' => 'container',
        '#attributes' => ['class' => ['checklist-report__item'], 'data-checklist-item' => $name],
        'heading' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['checklist-report__heading']],
          'title' => ['#type' => 'html_tag', '#tag' => 'h3', 'text' => ['#plain_text' => $history['title']]],
          'state' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#attributes' => ['class' => ['checklist-report__state'], 'data-state' => $state],
            'text' => ['#plain_text' => $this->stateLabel($state)],
          ],
        ],
        'method' => [
          '#type' => 'item',
          '#plain_text' => $item->getMethod() === ChecklistItemInterface::METHOD_AUTO
            ? $this->t('Automatic checklist item') : $this->t('Manual checklist item'),
        ],
      ];
      if ($history['attempt']) {
        $row['attempt'] = [
          '#type' => 'item',
          '#title' => $attempt === NULL ? $this->t('Latest execution') : $this->t('Selected execution'),
          '#plain_text' => $this->stateLabel($history['attempt']['status']),
        ];
      }
      $handler = $item->getHandler();
      if ($handler instanceof ExpectedOutcomeChecklistItemHandlerInterface && $item->get('outcomes')->access('view')) {
        if ($attempt !== NULL) {
          $row['outcome_notice'] = ['#plain_text' => $this->t('Outcomes show the item’s current recorded evidence, not a snapshot of the selected execution.')];
        }
        $row['outcomes'] = ['#type' => 'container', '#attributes' => ['class' => ['checklist-report__outcomes']]];
        foreach ($handler->expectedOutcomeDefinitions() as $key => $definition) {
          $value = $item->get('outcomes')->get($key);
          $recorded = $value instanceof ComplexDataInterface || $value instanceof ListInterface
            ? !$value->isEmpty() : $value->getValue() !== NULL;
          $content = $recorded && $preview ? $preview($item, $key) : [];
          $row['outcomes'][$key] = [
            '#type' => $content ? 'details' : 'item',
            '#title' => $definition->getLabel() ?: $key,
            'content' => $content ?: ['#plain_text' => $recorded ? $this->t('Recorded') : $this->t('Not recorded yet')],
          ];
        }
      }
      $row['history'] = $this->history($history, $field, $delta, $name, $attempt, $after);
      $build['items'][$name] = $row;
    }
    if (count($build['items']) === 2) {
      $build['items']['empty'] = ['#plain_text' => $this->t('No visible checklist items.')];
    }
    return $build;
  }

  /**
   * Formats stored states without evaluating live execution gates.
   */
  private function stateLabel(string $state): mixed {
    return match ($state) {
      'complete', 'succeeded' => $this->t('Complete'),
      'failed' => $this->t('Failed'),
      'running' => $this->t('Running'),
      'waiting' => $this->t('Waiting'),
      'queued' => $this->t('Queued'),
      'incomplete' => $this->t('Incomplete'),
      'cancelled' => $this->t('Cancelled'),
      'superseded' => $this->t('Superseded'),
      default => $state,
    };
  }

  /**
   * Validates a cursor scoped to this field, delta and visible item.
   */
  private function cursor(string $field, int $delta, string $name): array {
    $query = $this->requests->getCurrentRequest()?->query->all() ?? [];
    $cursors = $query['checklist_history'] ?? [];
    if (!is_array($cursors) || (isset($cursors[$field]) && !is_array($cursors[$field]))
      || (isset($cursors[$field][$delta]) && !is_array($cursors[$field][$delta]))) {
      throw new BadRequestHttpException('Invalid checklist history cursor.');
    }
    $cursor = $cursors[$field][$delta][$name] ?? [];
    if (!is_array($cursor)) {
      throw new BadRequestHttpException('Invalid checklist history cursor.');
    }
    $after = filter_var($cursor['after'] ?? 0, FILTER_VALIDATE_INT, [
      'options' => ['min_range' => 0, 'max_range' => PHP_INT_MAX - 50],
    ]);
    $attempt = $cursor['attempt'] ?? NULL;
    if ($after === FALSE || ($attempt !== NULL && (!is_string($attempt) || $attempt === '' || strlen($attempt) > 128))) {
      throw new BadRequestHttpException('Invalid checklist history cursor.');
    }
    return [$attempt, $after];
  }

  /**
   * Builds a bounded history page and pins navigation to the selected attempt.
   */
  private function history(array $history, string $field, int $delta, string $name, ?string $selected, int $after): array {
    $rows = [];
    foreach ($history['events'] as $event) {
      $rows[] = [
        ['data' => ['#plain_text' => $this->dates->format($event['created'], 'custom', 'm/d/Y H:i:s T')]],
        ['data' => ['#plain_text' => $this->stateLabel($event['to_status'])]],
        ['data' => ['#plain_text' => $event['reason']]],
      ];
    }
    $build = [
      '#type' => 'details',
      '#title' => $this->t('Execution history'),
      '#open' => $selected !== NULL || $after > 0,
      '#attributes' => ['class' => ['checklist-report__history']],
      'events' => [
        '#type' => 'table',
        '#header' => [$this->t('Time'), $this->t('State'), $this->t('Details')],
        '#rows' => $rows,
        '#empty' => $this->t('No execution attempts recorded yet.'),
      ],
    ];
    $attempt = $history['attempt'];
    if ($attempt) {
      $links = [];
      if ($history['next_after_version'] < $attempt['version']) {
        $links['later'] = [
          $this->t('Later events'),
          ['attempt' => $attempt['id'], 'after' => $history['next_after_version']],
        ];
      }
      if ($after > 0) {
        $links['first'] = [$this->t('First events'), ['attempt' => $attempt['id']]];
      }
      if ($attempt['previous']) {
        $links['previous'] = [$this->t('Previous attempt'), ['attempt' => $attempt['previous']]];
      }
      if ($selected !== NULL) {
        $links['latest'] = [$this->t('Latest attempt'), []];
      }
      foreach ($links as $key => [$label, $cursor]) {
        $query = $this->requests->getCurrentRequest()?->query->all() ?? [];
        $query['checklist_history'][$field][$delta][$name] = $cursor;
        $build['navigation'][$key] = [
          '#type' => 'link',
          '#title' => $label,
          '#url' => Url::fromRoute('<current>', [], ['query' => $query]),
        ];
      }
    }
    return $build;
  }

}
