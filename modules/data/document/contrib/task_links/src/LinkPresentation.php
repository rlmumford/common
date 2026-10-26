<?php

namespace Drupal\document_task_links;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\task\TaskInterface;

/**
 * Presents links only when both ends are visible and compatible.
 */
final class LinkPresentation {
  use StringTranslationTrait;

  /**
   * Constructs the presenter.
   */
  public function __construct(private readonly LinkIndex $index, private readonly EntityTypeManagerInterface $entities, private readonly DateFormatterInterface $dates) {}

  /**
   * Lists the task's input/output documents, including recorded former links.
   */
  public function documents(TaskInterface $task): array {
    if (!$task->access('view')) {
      return [];
    }
    $ids = array_unique(array_column($this->index->observations((int) $task->id()), 'document_id'));
    $items = [];
    foreach ($this->entities->getStorage('document')->loadMultiple($ids) as $document) {
      if ($document->access('view') && $this->index->compatible($task, $document)) {
        $items[] = [
          'document' => $document->hasLinkTemplate('canonical') ? Link::fromTextAndUrl($document->label(), $document->toUrl())->toRenderable() : ['#plain_text' => $document->label()],
          'relations' => $this->relations($task, (int) $document->id()),
        ];
      }
    }
    return $items ? [
      '#type' => 'details',
      '#title' => $this->t('Related documents'),
      '#open' => TRUE,
      '#cache' => ['max-age' => 0],
      'items' => ['#theme' => 'item_list', '#items' => $items],
    ] : [];
  }

  /**
   * Describes observations, never implying that dynamic references pin bytes.
   */
  public function relations(TaskInterface $task, int $document_id): array {
    $document = $this->entities->getStorage('document')->load($document_id);
    if (!$task->access('view') || !$document || !$document->access('view') || !$this->index->compatible($task, $document)) {
      return [];
    }
    $items = [];
    foreach ($this->index->observations((int) $task->id(), $document_id) as $link) {
      $label = $link['relation'] === 'input' ? $this->t('Input') : $this->t('Output');
      if ($link['removed']) {
        $label = $this->t('Former @direction', ['@direction' => $label]);
      }
      $details = [
        $label,
        $this->t('First recorded @date', ['@date' => $this->dates->format($link['first_seen'], 'short')]),
      ];
      if ($link['observed_revision']) {
        $details[] = $this->t('Observed revision @revision', ['@revision' => $link['observed_revision']]);
      }
      if ($link['removed']) {
        $details[] = $this->t('Link removed @date', ['@date' => $this->dates->format($link['removed'], 'short')]);
      }
      if ($link['source_type'] === 'checklist_item') {
        $source = $this->entities->getStorage('checklist_item')->load($link['source_id']);
        if ($source && $this->index->task($source)?->id() == $task->id() && $source->access('view action state') && $source->get('outcomes')->access('view')) {
          $details[] = $this->t('Checklist item: @item', ['@item' => $source->get('title')->value ?: $source->get('name')->value]);
        }
      }
      $items[] = ['#plain_text' => implode(' · ', $details)];
    }
    return ['#theme' => 'item_list', '#items' => $items, '#cache' => ['max-age' => 0]];
  }

}
