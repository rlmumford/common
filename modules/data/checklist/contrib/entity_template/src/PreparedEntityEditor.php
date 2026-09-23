<?php

namespace Drupal\checklist_entity_template;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Attempt\ChecklistAttemptClaims;
use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\checklist\Attempt\ChecklistAttemptJournal;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Execution\ChecklistItemExecutionPreparer;
use Drupal\checklist\Execution\ChecklistItemExecutor;
use Drupal\checklist_entity_template\Plugin\ChecklistItemHandler\CreateFromTemplate;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\flexiform\Api\ApiFormInterface;
use Drupal\flexiform\FormData\FormDataManagerFactory;
use Drupal\flexiform\FormFactory;
use Drupal\flexiform\FormPluginInterface;
use Drupal\flexiform\Session\FormSession;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Coordinates editing a prepared entity through HTML and action operations.
 *
 * The item state is authoritative. There is no second Flexiform tempstore copy.
 * Attempts pin the owner; takeover/recovery require a separate explicit action.
 */
class PreparedEntityEditor {

  public function __construct(
    protected ChecklistItemExecutionPreparer $preparer,
    protected ChecklistAttemptJournal $journal,
    protected ChecklistAttemptClaims $claims,
    protected ChecklistItemExecutor $executor,
    protected AccountProxyInterface $account,
    protected AccountSwitcherInterface $accountSwitcher,
    protected FormFactory $forms,
    protected FormDataManagerFactory $managers,
  ) {}

  /**
   * Resolves a reusable or embedded editor without loading provider values.
   */
  public function form(array $definition): FormPluginInterface {
    $form = $this->forms->create($definition['configuration'] ?? [], $definition['plugin'] ?? 'standard');
    // The checklist owns entity persistence. The editor changes that entity in
    // memory; other providers/savers/enhancers would introduce a second commit
    // boundary or external effects before the checklist result is fenced.
    $data = $form->getDataConfig();
    if (!$form instanceof ApiFormInterface || array_keys($data) !== ['entity'] || $data['entity']['plugin'] !== 'provided_data' || !empty($data['entity']['save_on_submit']) || $form->getFormEnhancers()) {
      throw new \InvalidArgumentException('A shared template editor requires one transient provided_data binding named entity and no save enhancers.');
    }
    return $form;
  }

  /**
   * Initializes the captured form plugin around the unsaved template result.
   */
  public function createSession(FormPluginInterface $form, FieldableEntityInterface $entity): FormSession {
    $session = new FormSession($form, $this->managers->create($form, ['entity' => $entity->getTypedData()]));
    $session->prepare();
    $description = $session->describe();
    if (empty($description['supported'])) {
      throw new \InvalidArgumentException($description['reason'] ?? 'The editor requires API-capable components.');
    }
    return $session;
  }

  /**
   * Reloads supported work and checks its owner without revealing working data.
   */
  protected function load(ChecklistItemInterface $identity): array {
    $this->preparer->executor((int) $this->account->id());
    [, $item] = $this->preparer->load($identity->uuid(), FALSE);
    if (!$item->getHandler() instanceof CreateFromTemplate || $item->getMethod() !== ChecklistItemInterface::METHOD_INTERACTIVE) {
      throw new \DomainException('This item has no shared template editor.');
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
   * Reads safe current data/schema, without starting or advancing any work.
   */
  public function describe(ChecklistItemInterface $identity): array {
    [$item, $attempt] = $this->load($identity);
    $metadata = ['id' => $item->uuid(), 'revision' => $attempt?->version ?? 0];
    if (!$attempt) {
      $choices = [];
      foreach ($item->getHandler()->available() as $key => $template) {
        $choices[$key] = (string) $template->label();
      }
      return $metadata + ['status' => $choices ? 'new' : 'unavailable', 'templates' => $choices];
    }
    if ($attempt->isTerminal()) {
      return $metadata + ['status' => $attempt->status === ChecklistAttempt::SUCCEEDED ? 'complete' : 'failed'];
    }
    if ($attempt->status === ChecklistAttempt::RUNNING) {
      return $metadata + ['status' => 'running'];
    }
    // Re-evaluate the item conditions before exposing editable working values.
    $this->preparer->prepare($item->uuid(), FALSE);
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
      if ($description['status'] === 'new') {
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
    $this->preparer->prepare($item->uuid(), FALSE);
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
    $allowed = $form_action ? ['revision', 'input'] : ($operation === 'start' ? ['revision', 'template'] : ['revision']);
    if (!is_int($parameters['revision'] ?? NULL) || array_diff(array_keys($parameters), $allowed) || ($form_action && !is_array($parameters['input'] ?? NULL))) {
      throw new \InvalidArgumentException('Supply the rendered revision and action input.');
    }
    [$item, $attempt] = $this->load($identity);
    if (($attempt?->version ?? 0) !== $parameters['revision']) {
      throw new ChecklistAttemptConflictException('The editor changed. Refresh before submitting again.');
    }
    [$item, $snapshot] = $this->preparer->prepare($item->uuid(), FALSE);
    $handler = $item->getHandler();
    if ($operation === 'start') {
      $available = $handler->available();
      $key = $parameters['template'] ?? (count($available) === 1 ? array_key_first($available) : NULL);
      if (!is_string($key)) {
        throw new \InvalidArgumentException('Select one of the available templates.');
      }
      $handler->select($key);
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
