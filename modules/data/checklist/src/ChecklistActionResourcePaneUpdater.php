<?php

namespace Drupal\checklist;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Render\RendererInterface;

/**
 * Rebuilds a checklist's resource pane in a checklist AJAX response.
 */
class ChecklistActionResourcePaneUpdater {

  /**
   * Constructs the updater.
   *
   * @param \Drupal\checklist\ChecklistActionResourceCollectorInterface $collector
   *   Collects current resources.
   * @param \Drupal\checklist\ChecklistActionResourcePaneBuilder $builder
   *   Builds the resource pane render array and stable DOM IDs.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Renders resource content and bubbles its attachments.
   */
  public function __construct(
    protected ChecklistActionResourceCollectorInterface $collector,
    protected ChecklistActionResourcePaneBuilder $builder,
    protected RendererInterface $renderer,
  ) {}

  /**
   * Replaces the pane and toggles the split workspace layout.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The response to update.
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The checklist whose pane must be refreshed.
   */
  public function refresh(AjaxResponse $response, ChecklistInterface $checklist): void {
    $resources = $this->collector->collect($checklist);
    $pane = $this->builder->build($resources, $checklist);
    $markup = $this->renderer->renderRoot($pane);
    $response->addAttachments($pane['#attached'] ?? []);
    $response->addCommand(new ReplaceCommand('#' . $this->builder->getPaneId($checklist), $markup));
    $method = $resources ? 'addClass' : 'removeClass';
    $response->addCommand(new InvokeCommand(
      '#' . $this->builder->getWorkspaceId($checklist),
      $method,
      ['checklist-workspace--resources']
    ));
  }

}
