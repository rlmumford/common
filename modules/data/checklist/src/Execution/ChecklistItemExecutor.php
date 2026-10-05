<?php

namespace Drupal\checklist\Execution;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Attempt\ChecklistAttemptClaims;
use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\checklist\Attempt\ChecklistAttemptJournal;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\BackgroundChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ActionStateChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\IterativeChecklistItemHandlerInterface;

/**
 * Submits and executes one item through the same audited inline/worker path.
 */
class ChecklistItemExecutor {

  /**
   * Constructs the automatic iteration runner.
   *
   * @param \Drupal\checklist\Execution\ChecklistItemExecutionPreparer $preparer
   *   Shared account, binding, access and gate preparation.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   Switches executor identity and restores the caller in finally blocks.
   * @param \Drupal\checklist\Attempt\ChecklistAttemptJournal $journal
   *   The attempt journal.
   * @param \Drupal\checklist\Attempt\ChecklistAttemptClaims $claims
   *   The iteration claim coordinator.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The submitting caller, never an arbitrary executor supplied by a client.
   * @param \Drupal\Core\Database\Connection $database
   *   The journal and item storage connection for atomic retry preparation.
   * @param \Drupal\checklist\Execution\ChecklistExecutionAuthorizer $authorizer
   *   Establishes and rechecks server-side execution authority.
   */
  public function __construct(
    protected ChecklistItemExecutionPreparer $preparer,
    protected AccountSwitcherInterface $accountSwitcher,
    protected ChecklistAttemptJournal $journal,
    protected ChecklistAttemptClaims $claims,
    protected AccountProxyInterface $currentUser,
    protected Connection $database,
    protected ChecklistExecutionAuthorizer $authorizer,
  ) {}

  /**
   * Authorizes initial work and runs it inline unless it must be deferred.
   *
   * Persist the item and host first. The entity is only an identity handle;
   * authoritative account, binding, access, contexts and gates are
   * loaded once for submission and execution, then rechecked at result commit.
   * Existing attempts are returned without execution, rebinding or retry.
   *
   * @param \Drupal\checklist\Entity\ChecklistItemInterface $item
   *   A saved autonomous item on a supported saved host binding.
   * @param bool $defer
   *   TRUE to record ready work for a worker. Background handlers and calls
   *   inside an outer transaction are always deferred, regardless of this flag.
   *
   * @return \Drupal\checklist\Attempt\ChecklistAttempt|null
   *   Existing, queued, waiting or finished attempt; NULL if not ready.
   */
  public function submit(ChecklistItemInterface $item, bool $defer = FALSE): ?ChecklistAttempt {
    if ($item->isNew()) {
      throw new \DomainException('Save the checklist item before submitting it.');
    }
    $delegated = FALSE;
    $account = $this->preparer->executor((int) $this->currentUser->id());
    $this->accountSwitcher->switchTo($account);
    try {
      [$checklist, $fresh] = $this->preparer->load($item->uuid());
      if ($existing = $this->journal->latest($fresh)) {
        return $existing;
      }
      $authorization = $this->authorizer->authorize($checklist, $fresh, (int) $account->id());
      if ($authorization->executor !== (int) $account->id()) {
        $this->accountSwitcher->switchTo($this->preparer->executor($authorization->executor));
        $delegated = TRUE;
        [$checklist, $fresh] = $this->preparer->load($item->uuid());
      }
      try {
        [, $snapshot] = $this->preparer->prepareItem($checklist, $fresh);
      }
      catch (ChecklistItemNotReadyException) {
        return NULL;
      }
      try {
        $attempt = $this->journal->create($fresh, (int) $account->id(), $authorization->executor, ChecklistAttempt::ACTION, authorization: $authorization->provenance);
      }
      catch (ChecklistAttemptConflictException $exception) {
        $existing = $this->journal->latest($fresh);
        if (!$existing) {
          throw $exception;
        }
        return $existing;
      }
      if ($defer || $fresh->getHandler() instanceof BackgroundChecklistItemHandlerInterface || !$this->claims->canAcquireCommittedClaim()) {
        return $attempt;
      }
      if ($authorization->provenance['source'] !== 'self') {
        // Preparation may have refreshed the job or resolved dynamic contexts.
        // Recheck delegated authority immediately before invoking the handler.
        $this->authorizer->authorize($checklist, $fresh, $attempt->initiator, $attempt);
      }
      return $this->execute($attempt, $fresh, $snapshot);
    }
    finally {
      if ($delegated) {
        $this->accountSwitcher->switchBack();
      }
      $this->accountSwitcher->switchBack();
    }
  }

  /**
   * Explicitly retries a failed automatic attempt with retained or fresh state.
   *
   * The expected ID/version must still identify the latest failed attempt for
   * this saved item. RESUME retains working state; FRESH clears it. Outcomes
   * and predecessor history are preserved. Both modes authorize the current
   * caller as initiator and reauthorize the executor, as on initial submission.
   *
   * Resetting the item and creating its successor commit together before any
   * inline provider call. An outer transaction forces deferral. Callers must
   * reconcile uncertain external effects before requesting either mode; a new
   * attempt ID does not undo effects from its predecessor.
   *
   * @param \Drupal\checklist\Entity\ChecklistItemInterface $item
   *   A saved autonomous item, used only as an identity handle.
   * @param \Drupal\checklist\Attempt\ChecklistAttempt $expected
   *   The failed attempt snapshot shown to the requesting caller.
   * @param string $mode
   *   ChecklistAttempt::RESUME or ChecklistAttempt::FRESH, explicitly chosen.
   * @param bool $defer
   *   TRUE to queue the successor without running its first iteration inline.
   *
   * @return \Drupal\checklist\Attempt\ChecklistAttempt
   *   The new queued, waiting or finished attempt.
   */
  public function retry(ChecklistItemInterface $item, ChecklistAttempt $expected, string $mode, bool $defer = FALSE): ChecklistAttempt {
    if ($item->isNew() || !in_array($mode, [ChecklistAttempt::RESUME, ChecklistAttempt::FRESH], TRUE)) {
      throw new \InvalidArgumentException('Retry requires a saved item and an explicit resume or fresh mode.');
    }
    $delegated = FALSE;
    $account = $this->preparer->executor((int) $this->currentUser->id());
    $this->accountSwitcher->switchTo($account);
    try {
      // Authorize before consulting history, then serialize successor creation
      // through the journal's conditional update of the latest-attempt pointer.
      [$checklist, $fresh] = $this->preparer->load($item->uuid());
      $authorization = $this->authorizer->authorize($checklist, $fresh, (int) $account->id());
      if ($authorization->executor !== (int) $account->id()) {
        $this->accountSwitcher->switchTo($this->preparer->executor($authorization->executor));
        $delegated = TRUE;
      }
      $transaction = $this->database->startTransaction();
      try {
        $previous = $this->journal->latest($item);
        if (!$previous || $previous->id !== $expected->id || $previous->version !== $expected->version) {
          throw new ChecklistAttemptConflictException('The failed attempt has changed.');
        }
        if ($previous->path !== ChecklistAttempt::ACTION || $previous->status !== ChecklistAttempt::FAILED) {
          throw new \DomainException('Only failed automatic attempts can be retried.');
        }
        $attempt = $this->journal->create($item, (int) $account->id(), $authorization->executor, ChecklistAttempt::ACTION, mode: $mode, previous: $previous->id, authorization: $authorization->provenance);
        // Reload after winning the predecessor fence, before resetting state.
        [$checklist, $fresh] = $this->preparer->load($item->uuid());
        if ($fresh->isComplete()) {
          throw new \DomainException('A completed item cannot be retried.');
        }
        $fresh->setIncomplete();
        $fresh->set('failure_method', []);
        if ($mode === ChecklistAttempt::FRESH) {
          $fresh->clearWorkingState();
        }
        $this->preparer->prepareItem($checklist, $fresh);
        $fresh->save();
      }
      catch (\Throwable $exception) {
        $transaction->rollBack();
        throw $exception;
      }
      unset($transaction);
      if ($defer || $fresh->getHandler() instanceof BackgroundChecklistItemHandlerInterface || !$this->claims->canAcquireCommittedClaim()) {
        return $attempt;
      }
      return $this->run($attempt);
    }
    finally {
      if ($delegated) {
        $this->accountSwitcher->switchBack();
      }
      $this->accountSwitcher->switchBack();
    }
  }

  /**
   * Executes one due iteration for an explicitly authorized automatic attempt.
   *
   * Internal worker entry point, not a user-facing dispatch API. The submitting
   * adapter authorizes the initial work/executor binding. This runner checks
   * the stored executor's current permissions, not the invoking cron account's.
   * Supports initial and explicitly retried action attempts on saved autonomous
   * items. Retry preparation is handled by retry(), never by worker delivery.
   *
   * @param \Drupal\checklist\Attempt\ChecklistAttempt $expected
   *   The expected attempt/version; all other metadata is reloaded.
   * @param int $lease_seconds
   *   Maximum iteration lease; this runner does not heartbeat blocking calls.
   *
   * @return \Drupal\checklist\Attempt\ChecklistAttempt
   *   Waiting, succeeded or failed attempt after local results are committed.
   *
   * @throws \Throwable
   *   Gate/access/configuration failures propagate. Handler exceptions record a
   *   safe failure, retain existing state and propagate the original error.
   */
  public function run(ChecklistAttempt $expected, int $lease_seconds = 300): ChecklistAttempt {
    $attempt = $this->journal->load($expected->id);
    if (!$attempt || $attempt->version !== $expected->version) {
      throw new ChecklistAttemptConflictException('The attempt version has changed.');
    }
    if ($attempt->path !== ChecklistAttempt::ACTION) {
      throw new \DomainException('This runner supports automatic action attempts only.');
    }
    $this->accountSwitcher->switchTo($this->preparer->executor($attempt->executor));
    try {
      [$checklist, $item] = $this->preparer->load($attempt->itemUuid);
      $this->authorizer->authorize($checklist, $item, $attempt->initiator, $attempt);
      [, $snapshot] = $this->preparer->prepareItem($checklist, $item);
      return $this->execute($attempt, $item, $snapshot, $lease_seconds);
    }
    finally {
      $this->accountSwitcher->switchBack();
    }
  }

  /**
   * Accepts input while preserving the attempt's executor and identity.
   *
   * Internal plugin API, not an endpoint accepting arbitrary state. The plugin
   * validates domain input and maps it to declared working-state names. Both
   * its action form and action operation should call this same method, passing
   * the attempt snapshot/version that accompanied the input request. No worker
   * or provider runs here; the existing scheduler picks up the continuation.
   */
  public function acceptInput(ChecklistItemInterface $item, ChecklistAttempt $expected, array $state): ChecklistAttempt {
    if ($expected->itemUuid !== $item->uuid() || $expected->path !== ChecklistAttempt::ACTION) {
      throw new \DomainException('Input requires the matching automatic attempt.');
    }
    $prepare = function () use ($item, $expected): ChecklistItemInterface {
      [$checklist, $fresh] = $this->preparer->load($item->uuid(), FALSE);
      $this->authorizer->authorize($checklist, $fresh, $expected->initiator, $expected);
      $this->preparer->prepareItem($checklist, $fresh);
      $handler = $fresh->getHandler();
      if ($fresh->getMethod() !== ChecklistItemInterface::METHOD_AUTO || !$handler instanceof IterativeChecklistItemHandlerInterface || !$handler instanceof ActionStateChecklistItemHandlerInterface || !$handler->getActionState()?->inputRequired) {
        throw new \DomainException('The automatic item is not requesting input.');
      }
      return $fresh;
    };
    $prepare();
    return $this->claims->acceptInput($expected, (int) $this->currentUser->id(), function () use ($prepare, $state): void {
      $this->apply($prepare(), new ChecklistItemResult(ChecklistAttempt::WAITING, state: $state));
    });
  }

  /**
   * Executes prepared work under a claim, with no transport-specific behavior.
   *
   * The caller has switched to the executor and prepared authoritative inputs.
   * Never expose prepared inputs as a public way to bypass authorization.
   */
  protected function execute(ChecklistAttempt $attempt, ChecklistItemInterface $item, array $snapshot, int $lease_seconds = 300): ChecklistAttempt {
    // Viewing/requesting human input must not repeatedly invoke the provider.
    // A due scheduler delivery is harmless while the handler requests input.
    $handler = $item->getHandler();
    if ($attempt->status === ChecklistAttempt::WAITING && $handler instanceof ActionStateChecklistItemHandlerInterface && $handler->getActionState()?->inputRequired) {
      return $attempt;
    }
    $claim = $this->claims->claim($attempt, $lease_seconds);
    $failure = NULL;
    try {
      $result = $item->getHandler()->actionIteration($claim->attempt);
    }
    catch (\Throwable $exception) {
      $failure = $exception;
      $result = new ChecklistItemResult(ChecklistAttempt::FAILED, reason: 'Automatic iteration failed.');
    }
    try {
      $finished = $this->claims->commit($claim, $result->status, function () use ($attempt, $snapshot, $result): void {
        // Account changes during the provider call must affect result access.
        $this->accountSwitcher->switchTo($this->preparer->executor($attempt->executor));
        try {
          [$checklist, $fresh] = $this->preparer->load($attempt->itemUuid);
          $this->authorizer->authorize($checklist, $fresh, $attempt->initiator, $attempt);
          [, $current] = $this->preparer->prepareItem($checklist, $fresh);
          if ($current !== $snapshot) {
            throw new ChecklistAttemptConflictException('The item or its execution inputs changed.');
          }
          $this->apply($fresh, $result);
        }
        finally {
          $this->accountSwitcher->switchBack();
        }
      }, $result->delay, $result->reason);
    }
    catch (\Throwable $exception) {
      // Release only our still-live claim. An expired/replaced claim remains
      // the supervisor's concern; never apply a stale result or retry work.
      try {
        $this->claims->commit($claim, ChecklistAttempt::FAILED, reason: 'Iteration result was not applied; reconciliation required.');
      }
      catch (ChecklistAttemptConflictException) {
        // Preserve the newer claim/history rather than masking the rejection.
      }
      throw $exception;
    }
    if ($failure) {
      throw $failure;
    }
    return $finished;
  }

  /**
   * Validates and saves declared result values under the claim transaction.
   *
   * Internal coordinator API: call only within claims->commit(), after fresh
   * authorization and input checks. This does not acquire a claim itself.
   */
  public function apply(ChecklistItemInterface $item, ChecklistItemResult $result, string $method = ChecklistItemInterface::METHOD_AUTO): void {
    if ($result->persist) {
      ($result->persist)();
    }
    foreach (['state' => $result->state, 'outcomes' => $result->outcomes] as $field => $values) {
      $list = $item->get($field);
      foreach ($values as $name => $value) {
        if (!is_string($name) || !array_key_exists($name, $list->getPropertyDefinitions())) {
          throw new \InvalidArgumentException('The iteration returned an undeclared state or outcome name.');
        }
        $list->set($name, $value);
        if ($list->get($name)->validate()->count()) {
          throw new \InvalidArgumentException('The iteration returned an invalid typed value.');
        }
      }
    }
    $item->setAttempted();
    if ($result->status === ChecklistAttempt::SUCCEEDED) {
      $item->setComplete($method);
    }
    elseif ($result->status === ChecklistAttempt::FAILED) {
      $item->setFailed($method);
    }
    $item->save();
  }

}
