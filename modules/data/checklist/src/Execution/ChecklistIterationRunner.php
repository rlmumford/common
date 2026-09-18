<?php

namespace Drupal\checklist\Execution;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Attempt\ChecklistAttemptClaims;
use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\checklist\Attempt\ChecklistAttemptJournal;
use Drupal\checklist\ChecklistContextPreparer;
use Drupal\checklist\ChecklistResolver;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ActionOperationsChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\InteractiveChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\IterativeChecklistItemHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Runs one claimed automatic iteration, then atomically applies local results.
 */
class ChecklistIterationRunner {

  /**
   * Constructs the automatic iteration runner.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity storage and access handlers.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   Switches executor identity and restores the caller in finally blocks.
   * @param \Drupal\checklist\ChecklistResolver $resolver
   *   Resolves accessible checklist fields.
   * @param \Drupal\checklist\ChecklistContextPreparer $contextPreparer
   *   Refreshes typed handler contexts.
   * @param \Drupal\checklist\Attempt\ChecklistAttemptJournal $journal
   *   The attempt journal.
   * @param \Drupal\checklist\Attempt\ChecklistAttemptClaims $claims
   *   The iteration claim coordinator.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountSwitcherInterface $accountSwitcher,
    protected ChecklistResolver $resolver,
    protected ChecklistContextPreparer $contextPreparer,
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
    $this->accountSwitcher->switchTo($this->executor($attempt));
    try {
      [$item, $snapshot] = $this->prepare($attempt);
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
          $this->accountSwitcher->switchTo($this->executor($attempt));
          try {
            [$fresh, $current] = $this->prepare($attempt);
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
   * Loads an active non-anonymous executor, discarding cached user/role data.
   */
  protected function executor(ChecklistAttempt $attempt) {
    $this->entityTypeManager->getStorage('user_role')->resetCache();
    $account = $this->entityTypeManager->getStorage('user')->loadUnchanged($attempt->executor);
    if (!$account || $account->isAnonymous() || !$account->isActive()) {
      throw new AccessDeniedHttpException('The execution account is unavailable.');
    }
    return $account;
  }

  /**
   * Reloads the target, checks gates and fingerprints its execution inputs.
   */
  protected function prepare(ChecklistAttempt $attempt): array {
    $storage = $this->entityTypeManager->getStorage('checklist_item');
    // Other items may supply outcomes changed by another worker/request.
    $storage->resetCache();
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('uuid', $attempt->itemUuid)->execute();
    if (count($ids) !== 1) {
      throw new \DomainException('The iteration requires a persisted checklist item.');
    }
    $item = $storage->loadUnchanged(reset($ids));
    $reference = $item->get('checklist');
    $host_type = $reference->getFieldDefinition()->getSetting('target_type');
    $host = $this->entityTypeManager->getStorage($host_type)->loadUnchanged($reference->target_id);
    if (!$host || $host->getEntityType()->isRevisionable()) {
      throw new \DomainException('The runner requires an existing non-revisionable host.');
    }
    $key = $reference->checklist_key;
    [$field, $delta] = array_pad(explode(':', $key, 2), 2, '0');
    if ($delta !== '0' || !$host->hasField($field)) {
      throw new \DomainException('The runner requires a single-value checklist field.');
    }
    $definition = $host->get($field)->getFieldDefinition();
    if ($definition->isTranslatable() || $definition->getFieldStorageDefinition()->getCardinality() !== 1) {
      throw new \DomainException('Multivalue and translated checklist bindings need a workspace adapter.');
    }
    $this->entityTypeManager->getAccessControlHandler($host_type)->resetCache();
    $this->entityTypeManager->getAccessControlHandler('checklist_item')->resetCache();
    $checklist = $this->resolver->resolve($host, $field, 0, 'update');
    if ($checklist->getType()->getPluginId() !== $item->bundle() || !$checklist->hasItem($item->getName()) || $checklist->getItem($item->getName())->uuid() !== $item->uuid()) {
      throw new \DomainException('The item no longer belongs to the addressed checklist.');
    }
    $reference->entity = $host;
    $checklist->setItem($item->getName(), $item);
    if (!$item->access('execute iteration')) {
      throw new AccessDeniedHttpException('The item cannot be executed.');
    }
    $handler = $item->getHandler();
    if (!$handler instanceof IterativeChecklistItemHandlerInterface || $handler instanceof ActionOperationsChecklistItemHandlerInterface || $handler instanceof InteractiveChecklistItemHandlerInterface || $item->getMethod() !== ChecklistItemInterface::METHOD_AUTO) {
      throw new \DomainException('The handler must support autonomous action iterations.');
    }
    if (!$item->isIncomplete() || !$this->contextPreparer->prepare($checklist, $item) || $item->isApplicable() !== TRUE || !$item->isActionable()) {
      throw new \DomainException('The checklist item is not ready for an iteration.');
    }
    $contexts = [];
    if ($handler instanceof ContextAwarePluginInterface) {
      foreach ($handler->getContexts() as $name => $context) {
        $contexts[$name] = $context->hasContextValue() ? $this->normalize($context->getContextValue()) : NULL;
      }
    }
    return [$item, [$item->toArray(), $host->toArray(), $contexts]];
  }

  /**
   * Normalizes entity/typed context values without serializing service caches.
   */
  protected function normalize($value) {
    if ($value instanceof EntityInterface) {
      return [$value->getEntityTypeId(), $value->uuid(), $this->normalize($value->toArray())];
    }
    if ($value instanceof TypedDataInterface) {
      return $this->normalize($value->getValue());
    }
    if (is_array($value)) {
      return array_map(fn($entry) => $this->normalize($entry), $value);
    }
    if (is_object($value) || is_resource($value)) {
      throw new \DomainException('Iteration contexts must have comparable typed values.');
    }
    return $value;
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
