<?php

namespace Drupal\checklist\Event;

use Drupal\checklist\ChecklistInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Lets integrations refresh controls alongside checklist rows and readiness.
 */
final class ChecklistRefreshEvent extends Event {

  public const NAME = 'checklist.refresh';

  /**
   * Constructs a workspace refresh event after its row commands are prepared.
   */
  public function __construct(public readonly AjaxResponse $response, public readonly ChecklistInterface $checklist) {}

}
