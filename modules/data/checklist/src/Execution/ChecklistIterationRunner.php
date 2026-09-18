<?php

namespace Drupal\checklist\Execution;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Attempt\ChecklistAttemptClaims;
use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\checklist\Attempt\ChecklistAttemptJournal;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Core\Session\AccountSwitcherInterface;

/**
 * Runs one claimed automatic iteration, then atomically applies local results.
 */
class ChecklistIterationRunner {

  /**
   * Constructs the automatic iteration runner.
   *
   * @param \Drupal\checklist\Execution\ChecklistIterationPreparer $preparer
   *   Shared account, binding, access and gate preparation.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   Switches executor identity and restores the caller in finally blocks.
   * @param \Drupal\checklist\Attempt\ChecklistAttemptJournal $journal
   *   The attempt journal.
   * @param \Drupal\checklist\Attempt\ChecklistAttemptClaims $claims
   *   The iteration claim coordinator.
   */
  public function __construct(
    protected ChecklistIterationPreparer $preparer,
    protected AccountSwitcherInterface $accountSwitcher,
    protected ChecklistAttemptJournal $journal,
    protected ChecklistAttemptClaims $claims,
  ) {}

  /**
   * Executes one due iteration for an explicitly authorized automatic attempt.
   *
   * Internal worker entry point, not a user-facing dispatch API. The submitting
   * adapter authorizes the initial work/executor binding. This runner checks
   * the stored executor's current permissions, not the invoking cron account's.
   * Only initial action attempts on persisted autonomous items are supported.
   * Successors require the future explicit resume/fresh-state coordinator.
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
    if ($attempt->path !== ChecklistAttempt::ACTION || $attempt->mode !== ChecklistAttempt::INITIAL) {
      throw new \DomainException('This runner supports initial automatic action attempts only.');
    }
    $this->accountSwitcher->switchTo($this->preparer->executor($attempt->executor));
    try {
      [$item, $snapshot] = $this->preparer->prepare($attempt->itemUuid);
      $claim = $this->claims->claim($attempt, $lease_seconds);
      $failure = NULL;
      try {
        $result = $item->getHandler()->actionIteration($claim->attempt);
      }
      catch (\Throwable $exception) {
        $failure = $exception;
        $result = new ChecklistIterationResult(ChecklistAttempt::FAILED, reason: 'Automatic iteration failed.');
      }
      try {
        $finished = $this->claims->commit($claim, $result->status, function () use ($attempt, $snapshot, $result): void {
          // Account changes during the provider call must affect result access.
          $this->accountSwitcher->switchTo($this->preparer->executor($attempt->executor));
          try {
            [$fresh, $current] = $this->preparer->prepare($attempt->itemUuid);
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
    finally {
      $this->accountSwitcher->switchBack();
    }
  }

  /**
   * Validates and saves declared result values under the claim transaction.
   */
  protected function apply(ChecklistItemInterface $item, ChecklistIterationResult $result): void {
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
      $item->setComplete(ChecklistItemInterface::METHOD_AUTO);
    }
    elseif ($result->status === ChecklistAttempt::FAILED) {
      $item->setFailed(ChecklistItemInterface::METHOD_AUTO);
    }
    $item->save();
  }

}
