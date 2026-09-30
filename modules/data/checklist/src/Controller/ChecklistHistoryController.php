<?php

namespace Drupal\checklist\Controller;

use Drupal\checklist\ChecklistItemReader;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Displays authorized attempt history without executing checklist work.
 */
class ChecklistHistoryController extends ControllerBase {

  /**
   * Constructs the read-only history page controller.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityManager,
    protected ChecklistItemReader $reader,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'), $container->get('checklist.item_reader'), $container->get('date.formatter'));
  }

  /**
   * Displays a bounded page of events from one attempt.
   */
  public function view(Request $request, string $entity_type, string $entity_id, string $checklist, string $item_name): array {
    if (!$this->entityManager->hasDefinition($entity_type) || !preg_match('/^([^:]+)(?::(0|[1-9][0-9]*))?$/D', $checklist, $parts)) {
      throw new NotFoundHttpException();
    }
    $host = $this->entityManager->getStorage($entity_type)->loadUnchanged($entity_id);
    if (!$host instanceof FieldableEntityInterface) {
      throw new NotFoundHttpException();
    }
    $query = $request->query->all();
    $after = filter_var($query['after_version'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $attempt_id = $query['attempt'] ?? NULL;
    if ($after === FALSE || ($attempt_id !== NULL && (!is_string($attempt_id) || $attempt_id === ''))) {
      throw new BadRequestHttpException('Invalid history cursor or attempt ID.');
    }
    $history = $this->reader->readHistory($host, $parts[1], (int) ($parts[2] ?? 0), $item_name, $attempt_id, $after, 25);
    $parameters = compact('entity_type', 'entity_id', 'checklist', 'item_name');
    $build = [
      '#title' => $this->t('History: @title', ['@title' => $history['title']]),
      '#cache' => ['max-age' => 0],
      '#attached' => ['library' => ['checklist/history']],
      '#attributes' => ['class' => ['checklist-history']],
      '#type' => 'container',
      'completion' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $history['completed'] !== NULL
          ? $this->t('Completed @date.', ['@date' => $this->dateFormatter->format($history['completed'], 'custom', 'j M Y H:i:s T')])
          : ($history['status'] === 'complete' ? $this->t('Completed; the completion time is unknown.') : $this->t('This item is not currently complete.')),
      ],
    ];
    $attempt = $history['attempt'];
    if (!$attempt) {
      $build['empty'] = ['#markup' => $this->t('No execution attempts have been recorded. Manual and synchronous actions may complete without an attempt history.')];
      return $build;
    }
    $account_ids = array_unique(array_merge(array_column($history['events'], 'actor'), [
      $attempt['initiator'], $attempt['executor'],
    ]));
    $accounts = $this->entityManager->getStorage('user')->loadMultiple($account_ids);
    $actor_label = fn(int $id) => isset($accounts[$id]) && $accounts[$id]->access('view') ? $accounts[$id]->label() : $this->t('User @id', ['@id' => $id]);
    $build['summary'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('@mode attempt — @status. Initiated by @initiator; executed as @executor.', [
        '@mode' => ucfirst($attempt['mode']),
        '@status' => $attempt['status'],
        '@initiator' => $actor_label($attempt['initiator']),
        '@executor' => $actor_label($attempt['executor']),
      ]),
    ];
    $build['explanation'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Events are shown oldest first. Waiting may mean a delay or a request for input. This is a history of execution transitions, not every internal step.'),
    ];
    $rows = [];
    foreach ($history['events'] as $event) {
      $rows[] = [
        [
          'data-label' => $this->t('Time'),
          'data' => ['#plain_text' => $this->dateFormatter->format($event['created'], 'custom', 'j M Y H:i:s T')],
        ],
        [
          'data-label' => $this->t('Transition'),
          'data' => ['#plain_text' => $event['from_status'] === NULL ? $event['to_status'] : $event['from_status'] . ' → ' . $event['to_status']],
        ],
        ['data-label' => $this->t('Actor'), 'data' => ['#plain_text' => (string) $actor_label($event['actor'])]],
        ['data-label' => $this->t('Reason'), 'data' => ['#plain_text' => $event['reason']]],
      ];
    }
    $build['events'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['checklist-history-events'],
        'tabindex' => '0',
        'role' => 'region',
        'aria-label' => $this->t('Execution events'),
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Time'), $this->t('Transition'), $this->t('Actor'), $this->t('Reason')],
        '#rows' => $rows,
        '#empty' => $this->t('No later events have been recorded.'),
      ],
    ];
    $build['navigation'] = ['#type' => 'container', '#attributes' => ['class' => ['checklist-history-navigation']]];
    // Pin the attempt for pagination even if another attempt starts meanwhile.
    if ($history['next_after_version'] < $attempt['version']) {
      $build['navigation']['next'] = $this->link($this->t('Later events'), $parameters, [
        'attempt' => $attempt['id'],
        'after_version' => $history['next_after_version'],
      ]);
    }
    if ($after > 0) {
      $build['navigation']['start'] = $this->link($this->t('First events'), $parameters, ['attempt' => $attempt['id']]);
    }
    if ($attempt['previous']) {
      $build['navigation']['previous'] = $this->link($this->t('Previous attempt'), $parameters, ['attempt' => $attempt['previous']]);
    }
    if ($attempt_id !== NULL || $after > 0) {
      $build['navigation']['latest'] = $this->link($this->t('Latest attempt'), $parameters);
    }
    return $build;
  }

  /**
   * Builds navigation using the authorized item's address.
   */
  protected function link($title, array $parameters, array $query = []): array {
    return [
      '#type' => 'link',
      '#title' => $title,
      '#url' => Url::fromRoute('checklist.item.history_page', $parameters, ['query' => $query]),
      '#attributes' => [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'dialog',
        'data-dialog-renderer' => 'off_canvas',
        'data-dialog-options' => json_encode(['width' => 640, 'classes' => ['ui-dialog' => 'checklist-history-dialog']]),
      ],
      '#attached' => ['library' => ['core/drupal.dialog.ajax']],
    ];
  }

}
