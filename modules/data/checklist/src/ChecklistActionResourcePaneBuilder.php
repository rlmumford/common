<?php

namespace Drupal\checklist;

use Drupal\Component\Utility\Html;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Builds a stable, accessible render array for a checklist resource pane.
 */
class ChecklistActionResourcePaneBuilder {

  /**
   * Gets the DOM ID for a checklist's resource pane.
   */
  public function getPaneId(ChecklistInterface $checklist): string {
    return Html::getId('checklist-resource-pane-' . $checklist->getEntity()->uuid() . '-' . $checklist->getKey());
  }

  /**
   * Gets the DOM ID for the checklist workspace containing the pane.
   */
  public function getWorkspaceId(ChecklistInterface $checklist): string {
    return Html::getId('checklist-workspace-' . $checklist->getEntity()->uuid() . '-' . $checklist->getKey());
  }

  /**
   * Gets a collision-resistant ID for one resource panel.
   */
  public function getResourcePanelId(ChecklistInterface $checklist, string $key): string {
    return Html::getId($this->getPaneId($checklist) . '-' . $key . '-' . substr(hash('sha256', $key), 0, 8));
  }

  /**
   * Builds the pane and its hidden placeholder when no resources are available.
   *
   * @param array $resources
   *   Resources returned by the checklist resource collector.
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The checklist whose workspace is being built.
   *
   * @return array
   *   A render array for the stable resource region.
   */
  public function build(array $resources, ChecklistInterface $checklist): array {
    $pane_id = $this->getPaneId($checklist);
    if (!$resources) {
      return [
        '#type' => 'container',
        '#attributes' => [
          'id' => $pane_id,
          'class' => ['checklist-resource-pane'],
          'hidden' => TRUE,
          'data-has-resources' => 'false',
        ],
        '#cache' => ['max-age' => 0],
      ];
    }

    uasort($resources, static function (array $a, array $b): int {
      return $a['resource']->getWeight() <=> $b['resource']->getWeight();
    });
    $navigation = [];
    $panels = [];
    $first = TRUE;
    foreach ($resources as $key => $entry) {
      $resource = $entry['resource'];
      $panel_id = $this->getResourcePanelId($checklist, $key);
      $navigation[] = [
        '#type' => 'button',
        '#value' => $resource->getLabel() ?? reset($entry['owners']),
        '#attributes' => [
          'type' => 'button',
          'class' => ['checklist-resource-select'],
          'data-resource-key' => $key,
          'aria-controls' => $panel_id,
          'aria-pressed' => $first ? 'true' : 'false',
        ],
      ];
      $panels[$key] = [
        '#type' => 'container',
        '#weight' => $resource->getWeight(),
        '#attributes' => [
          'id' => $panel_id,
          'class' => ['checklist-resource-content'],
          'data-resource-key' => $key,
          'data-resource-owners' => implode(' ', $entry['owners']),
          'data-resource-closeable' => $resource->isCloseable() ? 'true' : 'false',
          'data-resource-icon' => $resource->getIcon() ?? '',
          'data-resource-pinned' => $resource->isPinned() ? 'true' : 'false',
          'hidden' => !$first,
        ],
        'content' => $resource->getContent(),
      ];
      $first = FALSE;
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => $pane_id,
        'class' => ['checklist-resource-pane'],
        'role' => 'complementary',
        'aria-label' => new TranslatableMarkup('Checklist resources'),
        'data-has-resources' => 'true',
      ],
      '#cache' => ['max-age' => 0],
      'navigation' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['checklist-resource-navigation'], 'role' => 'group'],
        'items' => $navigation,
      ],
      'panels' => $panels,
    ];
  }

}
