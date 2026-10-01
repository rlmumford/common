<?php

namespace Drupal\checklist_flexiform;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\ChecklistResolver;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\checklist\Attempt\ChecklistAttemptClaims;
use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\checklist\Attempt\ChecklistAttemptJournal;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Execution\ChecklistItemExecutionPreparer;
use Drupal\checklist\Execution\ChecklistItemExecutor;
use Drupal\checklist_flexiform\Plugin\ChecklistItemHandler\FormItemHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Coordinates checklist forms through HTML and action operations.
 *
 * The item state is authoritative. There is no second Flexiform tempstore copy.
 * Attempts pin the owner; takeover/recovery require a separate explicit action.
 */
class ChecklistFormEditor {

  public function __construct(
    protected ChecklistItemExecutionPreparer $preparer,
    protected ChecklistAttemptJournal $journal,
    protected ChecklistAttemptClaims $claims,
    protected ChecklistItemExecutor $executor,
    protected AccountProxyInterface $account,
    protected AccountSwitcherInterface $accountSwitcher,
    protected EntityTypeManagerInterface $entityTypes,
    protected ChecklistResolver $resolver,
    protected LockBackendInterface $lock,
  ) {}

  /**
   * Reloads supported work and checks its owner without revealing working data.
   */
  protected function load(ChecklistItemInterface $identity): array {
    $this->preparer->executor((int) $this->account->id());
    $item = $identity->isNew() ? $this->resolveVirtualItem($identity) : $this->preparer->load($identity->uuid(), FALSE)[1];
    if (!$item->getHandler() instanceof FormItemHandlerInterface || $item->getMethod() !== ChecklistItemInterface::METHOD_INTERACTIVE) {
      throw new \DomainException('This item has no shared form session.');
    }
    $attempt = $this->journal->latest($item);
    if ($attempt && (
      $attempt->executor !== (int) $this->account->id() ||
      !in_array($attempt->path, [ChecklistAttempt::ACTION_FORM, ChecklistAttempt::ACTION_OPERATION], TRUE) ||
      ($attempt->path === ChecklistAttempt::ACTION_OPERATION && $attempt->operation !== 'editor')
    )) {
      throw new AccessDeniedHttpException('This checklist editor belongs to another attempt or user.');
    }
    return [$item, $attempt];
  }

  /**
   * Resolves virtual work by its named checklist address on a fresh saved host.
   */
  protected function resolveVirtualItem(ChecklistItemInterface $identity): ChecklistItemInterface {
    $reference = $identity->get('checklist');
    $host = $reference->entity;
    if (!$host || $host->isNew()) {
      throw new \DomainException('Save the checklist host before opening its form.');
    }
    $host = $this->entityTypes->getStorage($host->getEntityTypeId())->loadUnchanged($host->id());
    if (!$host) {
      throw new \DomainException('The checklist host no longer exists.');
    }
    [$field, $delta] = array_pad(explode(':', $reference->checklist_key, 2), 2, '0');
    $checklist = $this->resolver->resolve($host, $field, (int) $delta, 'update');
    $item = $checklist->getItem($identity->getName());
    if (!$item || !$item->access('execute action operation')) {
      throw new AccessDeniedHttpException('The checklist form is unavailable.');
    }
    return $item;
  }

  /**
   * Persists first interaction once, before creating the audited form attempt.
   */
  protected function materialize(ChecklistItemInterface $identity): ChecklistItemInterface {
    $reference = $identity->get('checklist');
    $host = $reference->entity;
    $key = 'checklist_form:' . hash('sha256', $host->uuid() . ':' . $reference->checklist_key . ':' . $identity->getName());
    if (!$this->lock->acquire($key)) {
      throw new ChecklistAttemptConflictException('The form is being opened. Refresh before trying again.');
    }
    try {
      $item = $this->resolveVirtualItem($identity);
      if ($item->isNew()) {
        $checklist = $item->get('checklist')->checklist;
        $this->preparer->prepareItem($checklist, $item);
        $item->save();
      }
      return $item;
    }
    finally {
      $this->lock->release($key);
    }
  }

  /**
   * Reads safe current data/schema, without starting or advancing any work.
   */
  public function describe(ChecklistItemInterface $identity): array {
    [$item, $attempt] = $this->load($identity);
    $metadata = ['id' => $item->uuid(), 'revision' => $attempt?->version ?? 0];
    if (!$attempt) {
      $choices = $item->getHandler()->editorChoices();
      return $metadata + ['status' => $choices === [] ? 'unavailable' : 'new'] + ($choices === NULL ? [] : ['templates' => $choices]);
    }
    if ($attempt->isTerminal()) {
      return $metadata + ['status' => $attempt->status === ChecklistAttempt::SUCCEEDED ? 'complete' : 'failed'];
    }
    if ($attempt->status === ChecklistAttempt::RUNNING) {
      return $metadata + ['status' => 'running'];
    }
    // Re-evaluate the item conditions before exposing editable working values.
    [$item] = $this->preparer->prepare($item->uuid(), FALSE);
    $editor = $item->getHandler()->getEditorSession();
    return $metadata + ($editor ? $editor[0]->describe() : ['status' => 'preparing']);
  }

  /**
   * Discovers the same operations used by HTML and non-browser adapters.
   */
  public function operations(ChecklistItemInterface $identity): array {
    $description = $this->describe($identity);
    $revision = ['type' => 'integer', 'minimum' => 0];
    $operations = [
      'get' => [
        'label' => 'Read editor',
        'description' => 'Read the current editor status, values and accepted input schema.',
        'parameters_schema' => ['type' => 'object', 'additionalProperties' => FALSE],
      ],
    ];
    if (in_array($description['status'], ['new', 'preparing'], TRUE)) {
      $properties = ['revision' => $revision];
      $required = ['revision'];
      if ($description['status'] === 'new' && isset($description['templates'])) {
        $properties['template'] = ['type' => 'string', 'enum' => array_keys($description['templates'])];
        if (count($description['templates']) !== 1) {
          $required[] = 'template';
        }
      }
      $operations[$description['status'] === 'new' ? 'start' : 'advance'] = [
        'label' => $description['status'] === 'new' ? 'Open editor' : 'Check preparation',
        'description' => 'Run one bounded preparation pass without saving the entity.',
        'parameters_schema' => [
          'type' => 'object',
          'properties' => $properties,
          'required' => $required,
          'additionalProperties' => FALSE,
        ],
      ];
    }
    foreach ($description['actions'] ?? [] as $action) {
      $operations['form/' . $action['id']] = [
        'label' => $action['label'],
        'description' => 'Apply this advertised form action to the current working revision.',
        'parameters_schema' => [
          'type' => 'object',
          'properties' => ['revision' => $revision, 'input' => $action['input_schema']],
          'required' => ['revision', 'input'],
          'additionalProperties' => FALSE,
        ],
      ];
    }
    return $operations;
  }

  /**
   * Validates HTML input against a detached session without acquiring a claim.
   */
  public function validateInput(ChecklistItemInterface $identity, int $revision, string $action, array $input): void {
    [$item, $attempt] = $this->load($identity);
    if (!$attempt || $attempt->version !== $revision) {
      throw new ChecklistAttemptConflictException('The editor changed. Refresh before submitting again.');
    }
    [$item] = $this->preparer->prepare($item->uuid(), FALSE);
    $editor = $item->getHandler()->getEditorSession();
    if (!$editor) {
      throw new \DomainException('The editor is not ready.');
    }
    $editor[0]->validate($action, $input);
  }

  /**
   * Executes one revision-checked operation as the authenticated editor owner.
   */
  public function operate(ChecklistItemInterface $identity, string $operation, array $parameters, string $entry_path = ChecklistAttempt::ACTION_OPERATION): array {
    if (!in_array($entry_path, [ChecklistAttempt::ACTION_FORM, ChecklistAttempt::ACTION_OPERATION], TRUE)) {
      throw new \InvalidArgumentException('Unknown editor entry path.');
    }
    $this->accountSwitcher->switchTo($this->preparer->executor((int) $this->account->id()));
    try {
      return $this->perform($identity, $operation, $parameters, $entry_path);
    }
    finally {
      $this->accountSwitcher->switchBack();
    }
  }

  /**
   * Keeps expensive preparation outside the short result transaction.
   */
  protected function perform(ChecklistItemInterface $identity, string $operation, array $parameters, string $entry_path): array {
    if (!isset($this->operations($identity)[$operation])) {
      throw new \DomainException('The editor operation is unavailable.');
    }
    if ($operation === 'get') {
      if ($parameters) {
        throw new \InvalidArgumentException('Read does not accept parameters.');
      }
      return $this->describe($identity);
    }
    $form_action = str_starts_with($operation, 'form/');
    $allowed = $form_action ? ['revision', 'input'] : ['revision'];
    if ($operation === 'start') {
      $allowed[] = 'template';
    }
    if (!is_int($parameters['revision'] ?? NULL) || array_diff(array_keys($parameters), $allowed) || ($form_action && !is_array($parameters['input'] ?? NULL))) {
      throw new \InvalidArgumentException('Supply the rendered revision and action input.');
    }
    if ($identity->isNew()) {
      if ($operation !== 'start' || $parameters['revision'] !== 0) {
        throw new ChecklistAttemptConflictException('Start the form before applying input.');
      }
      $identity = $this->materialize($identity);
    }
    [$item, $attempt] = $this->load($identity);
    if (($attempt?->version ?? 0) !== $parameters['revision']) {
      throw new ChecklistAttemptConflictException('The editor changed. Refresh before submitting again.');
    }
    [$item, $snapshot] = $this->preparer->prepare($item->uuid(), FALSE);
    $handler = $item->getHandler();
    if ($operation === 'start') {
      $available = $handler->editorChoices();
      if ($available !== NULL) {
        $key = $parameters['template'] ?? (count($available) === 1 ? array_key_first($available) : NULL);
        if (!is_string($key)) {
          throw new \InvalidArgumentException('Select one of the available templates.');
        }
        $handler->selectEditorChoice($key);
      }
      elseif (isset($parameters['template'])) {
        throw new \InvalidArgumentException('This form does not accept a template choice.');
      }
    }
    $session = NULL;
    if ($form_action) {
      [$session, $editable] = $handler->getEditorSession();
      // This graph was deserialized from private item state and is detached.
      // Invalid input never acquires a claim or replaces retained working data.
      $action = substr($operation, 5);
      $session->validate($action, $parameters['input']);
    }
    if (!$attempt) {
      $attempt = $this->journal->create($item, (int) $this->account->id(), (int) $this->account->id(), $entry_path, $entry_path === ChecklistAttempt::ACTION_OPERATION ? 'editor' : NULL);
    }
    $claim = $this->claims->claim($attempt);
    try {
      $result = $session
        ? $handler->editorResult($session, $editable, $session->execute($action, $parameters['input']))
        : $handler->actionIteration($claim->attempt);
      $this->claims->commit($claim, $result->status, function () use ($item, $snapshot, $result, $attempt): void {
        $this->accountSwitcher->switchTo($this->preparer->executor($attempt->executor));
        try {
          [$fresh, $current] = $this->preparer->prepare($item->uuid(), FALSE);
          if ($current !== $snapshot) {
            throw new ChecklistAttemptConflictException('The checklist item or inputs changed during editing.');
          }
          $this->executor->apply($fresh, $result, ChecklistItemInterface::METHOD_INTERACTIVE);
        }
        finally {
          $this->accountSwitcher->switchBack();
        }
      }, reason: 'Editor ' . $entry_path . ': ' . $operation . '. ' . $result->reason);
    }
    catch (\Throwable $exception) {
      try {
        $this->claims->commit($claim, ChecklistAttempt::FAILED, reason: 'Editor operation failed; retained state requires reconciliation.');
      }
      catch (ChecklistAttemptConflictException) {
        // A newer or expired claim must not be overwritten by this request.
      }
      throw $exception;
    }
    return $this->describe($identity);
  }

}
