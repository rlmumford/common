<?php

namespace Drupal\checklist\Execution;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\checklist\Attempt\ChecklistAttemptJournal;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountSwitcherInterface;

/**
 * Authorizes initial automatic work as the current authenticated caller.
 */
class ChecklistIterationSubmitter {

  /**
   * Constructs the iteration submitter.
   *
   * @param \Drupal\checklist\Execution\ChecklistIterationPreparer $preparer
   *   Shared authoritative account, binding, access and gate preparation.
   * @param \Drupal\checklist\Attempt\ChecklistAttemptJournal $journal
   *   Atomically records the first attempt for an item.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The submitting caller; this entry point does not accept another executor.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   Uses fresh account data during authorization, restoring the caller.
   */
  public function __construct(
    protected ChecklistIterationPreparer $preparer,
    protected ChecklistAttemptJournal $journal,
    protected AccountProxyInterface $currentUser,
    protected AccountSwitcherInterface $accountSwitcher,
  ) {}

  /**
   * Submits a saved item without executing it or writing to the queue.
   *
   * The supplied entity is an identity handle: saved configuration and host
   * data are reloaded. Save edits first. Access is checked before returning any
   * existing attempt. Its executor, history and state remain unchanged, even
   * after failure; explicit retry/reset requires a separate coordinator.
   *
   * Initial submissions require current applicability/actionability and all
   * required contexts. The runner repeats these checks at execution time.
   * Journal creation can participate in a caller's transaction. Dispatch must
   * wait for commit; cron discovers committed attempts without a queue write
   * here, so rollback cannot leave a runnable orphan message.
   *
   * @param \Drupal\checklist\Entity\ChecklistItemInterface $item
   *   A saved autonomous item on a supported saved host binding.
   *
   * @return \Drupal\checklist\Attempt\ChecklistAttempt|null
   *   The existing or new attempt, or NULL when not currently ready.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   If the caller is unavailable or cannot execute the item.
   * @throws \DomainException
   *   If the binding or handler is unsupported. No implicit item save occurs.
   */
  public function submit(ChecklistItemInterface $item): ?ChecklistAttempt {
    if ($item->isNew()) {
      throw new \DomainException('Save the checklist item before submitting it.');
    }
    $account = $this->preparer->executor((int) $this->currentUser->id());
    $this->accountSwitcher->switchTo($account);
    try {
      [, $fresh] = $this->preparer->load($item->uuid());
      if ($existing = $this->journal->latest($fresh)) {
        return $existing;
      }
      try {
        [$fresh] = $this->preparer->prepare($item->uuid());
      }
      catch (ChecklistIterationNotReadyException) {
        return NULL;
      }
      try {
        return $this->journal->create($fresh, (int) $account->id(), (int) $account->id(), ChecklistAttempt::ACTION);
      }
      catch (ChecklistAttemptConflictException $exception) {
        // A competing authorized submission may have recorded the first
        // attempt after our read. Adopt its identity without rebinding it.
        $existing = $this->journal->latest($fresh);
        if (!$existing) {
          throw $exception;
        }
        return $existing;
      }
    }
    finally {
      $this->accountSwitcher->switchBack();
    }
  }

}
