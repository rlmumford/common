<?php

namespace Drupal\checklist\Execution;

use Drupal\checklist\ChecklistContextPreparer;
use Drupal\checklist\ChecklistInterface;
use Drupal\checklist\ChecklistResolver;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ActionOperationsChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\InteractiveChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\IterativeChecklistItemHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Shared authoritative account, binding, access and gate checks for iterations.
 */
class ChecklistItemExecutionPreparer {

  /**
   * Constructs the iteration preparer.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity storage and access handlers.
   * @param \Drupal\checklist\ChecklistResolver $resolver
   *   Resolves accessible checklist fields.
   * @param \Drupal\checklist\ChecklistContextPreparer $contextPreparer
   *   Refreshes typed handler contexts.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ChecklistResolver $resolver,
    protected ChecklistContextPreparer $contextPreparer,
  ) {}

  /**
   * Loads an active non-anonymous executor, discarding cached user/role data.
   */
  public function executor(int $uid) {
    $this->entityTypeManager->getStorage('user_role')->resetCache();
    $account = $this->entityTypeManager->getStorage('user')->loadUnchanged($uid);
    if (!$account || $account->isAnonymous() || !$account->isActive()) {
      throw new AccessDeniedHttpException('The execution account is unavailable.');
    }
    return $account;
  }

  /**
   * Loads and authorizes a saved autonomous item under the current account.
   *
   * @return array
   *   The authoritative checklist and item, without evaluating item gates.
   */
  public function load(string $item_uuid): array {
    $storage = $this->entityTypeManager->getStorage('checklist_item');
    // Other items may supply outcomes changed by another worker/request.
    $storage->resetCache();
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('uuid', $item_uuid)->execute();
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
    return [$checklist, $item];
  }

  /**
   * Reloads, authorizes and prepares a ready item's inputs for execution.
   *
   * @return array
   *   The fresh item and a comparable execution-input snapshot.
   *
   * @throws \Drupal\checklist\Execution\ChecklistItemNotReadyException
   *   If incomplete state, required context or item conditions prevent work.
   */
  public function prepare(string $item_uuid): array {
    [$checklist, $item] = $this->load($item_uuid);
    return $this->prepareItem($checklist, $item);
  }

  /**
   * Prepares an already loaded/authorized binding without reloading it again.
   *
   * Internal execution helper; callers must first use load() for authorization.
   */
  public function prepareItem(ChecklistInterface $checklist, ChecklistItemInterface $item): array {
    $host = $checklist->getEntity();
    $handler = $item->getHandler();
    if (!$item->isIncomplete() || !$this->contextPreparer->prepare($checklist, $item) || $item->isApplicable() !== TRUE || !$item->isActionable()) {
      throw new ChecklistItemNotReadyException('The checklist item is not ready for an iteration.');
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

}
