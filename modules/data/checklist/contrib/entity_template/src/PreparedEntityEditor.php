<?php

namespace Drupal\checklist_entity_template;

use Drupal\checklist_flexiform\ChecklistFormEditor;
use Drupal\checklist\ChecklistResolver;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;

use Drupal\checklist\Attempt\ChecklistAttemptClaims;
use Drupal\checklist\Attempt\ChecklistAttemptJournal;
use Drupal\checklist\Execution\ChecklistItemExecutionPreparer;
use Drupal\checklist\Execution\ChecklistItemExecutor;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\flexiform\Api\ApiFormInterface;
use Drupal\flexiform\FormData\FormDataManagerFactory;
use Drupal\flexiform\FormFactory;
use Drupal\flexiform\FormPluginInterface;
use Drupal\flexiform\Session\FormSession;

/**
 * Coordinates editing a prepared entity through HTML and action operations.
 *
 * The item state is authoritative. There is no second Flexiform tempstore copy.
 * Attempts pin the owner; takeover/recovery require a separate explicit action.
 */
class PreparedEntityEditor extends ChecklistFormEditor {

  public function __construct(
    ChecklistItemExecutionPreparer $preparer,
    ChecklistAttemptJournal $journal,
    ChecklistAttemptClaims $claims,
    ChecklistItemExecutor $executor,
    AccountProxyInterface $account,
    AccountSwitcherInterface $accountSwitcher,
    protected FormFactory $forms,
    protected FormDataManagerFactory $managers,
    EntityTypeManagerInterface $entityTypes,
    ChecklistResolver $resolver,
    LockBackendInterface $lock,
  ) {
    parent::__construct($preparer, $journal, $claims, $executor, $account, $accountSwitcher, $entityTypes, $resolver, $lock);
  }

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

}
