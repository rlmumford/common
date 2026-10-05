<?php

namespace Drupal\checklist\Event;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\ChecklistInterface;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Execution\ChecklistExecutionAuthorization;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Lets trusted integrations authorize a specific source of automatic work.
 */
final class ChecklistExecutionAuthorizationEvent extends Event {

  public const NAME = 'checklist.execution_authorization';

  /**
   * The authorization established by a trusted subscriber, if any.
   *
   * @var \Drupal\checklist\Execution\ChecklistExecutionAuthorization|null
   */
  public ?ChecklistExecutionAuthorization $authorization = NULL;

  public function __construct(
    public readonly ChecklistInterface $checklist,
    public readonly ChecklistItemInterface $item,
    public readonly int $initiator,
    public readonly ?ChecklistAttempt $attempt = NULL,
  ) {}

}
