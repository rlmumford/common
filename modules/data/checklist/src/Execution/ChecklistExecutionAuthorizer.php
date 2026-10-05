<?php

namespace Drupal\checklist\Execution;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\ChecklistInterface;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Event\ChecklistExecutionAuthorizationEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Resolves server-side authorization; item/client configuration grants nothing.
 */
class ChecklistExecutionAuthorizer {

  public function __construct(protected EventDispatcherInterface $events) {}

  /**
   * Authorizes submission or revalidates the immutable authority of an attempt.
   */
  public function authorize(ChecklistInterface $checklist, ChecklistItemInterface $item, int $initiator, ?ChecklistAttempt $attempt = NULL): ChecklistExecutionAuthorization {
    $event = new ChecklistExecutionAuthorizationEvent($checklist, $item, $initiator, $attempt);
    $this->events->dispatch($event, ChecklistExecutionAuthorizationEvent::NAME);
    $authorization = $event->authorization ?? new ChecklistExecutionAuthorization($initiator, [
      'source' => 'self',
      'authorizer' => $initiator,
    ]);
    if ($attempt) {
      // Legacy self-execution remains valid. Delegated legacy rows have no
      // recorded grant, so they cannot establish authority.
      $recorded = $attempt->authorization ?: ['source' => 'self', 'authorizer' => $attempt->initiator];
      if ($authorization->executor !== $attempt->executor || $authorization->provenance !== $recorded) {
        throw new AccessDeniedHttpException('The execution authorization has changed or been revoked.');
      }
    }
    return $authorization;
  }

}
