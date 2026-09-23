<?php

namespace Drupal\checklist_entity_template\Plugin\ChecklistItemHandler;

use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\entity_template\Plugin\EntityTemplate\Template\Template;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Applies a template to detached working data, saving only after completion.
 *
 * @ChecklistItemHandler(
 *   id = "entity_template__apply_to",
 *   label = @Translation("Apply template to entity"),
 *   category = @Translation("Entity Template"),
 *   forms = {
 *     "row" = "\Drupal\checklist_entity_template\PluginForm\TemplateItemRowForm",
 *     "action" = "\Drupal\checklist_entity_template\PluginForm\PreparedEntityEditorActionForm",
 *   }
 * )
 */
class ApplyTemplateToEntity extends TemplateItemBase {

  /**
   * {@inheritdoc}
   */
  public function getContextDefinitions() {
    $output = $this->expectedOutcomeDefinitions()['entity'];
    $target = new EntityContextDefinition($output->getDataType(), $this->t('Entity to update'), FALSE);
    $target->setConstraints($output->getConstraints());
    // Missing targets make every candidate unavailable, rather than creating.
    return ['target' => $target] + parent::getContextDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function getContextMapping() {
    return array_intersect_key($this->configuration['context_mapping'] ?? [], ['target' => TRUE]) + parent::getContextMapping();
  }

  /**
   * {@inheritdoc}
   */
  protected function templateApplies(Template $template): bool {
    $target = $this->getContextValue('target');
    if (!$target instanceof FieldableEntityInterface || $target->isNew()) {
      return FALSE;
    }
    $definition = $template->getTargetDefinition();
    $bundles = $definition->getConstraint('Bundle');
    if ($target->getEntityTypeId() !== $definition->getEntityTypeId() || ($bundles && !in_array($target->bundle(), (array) $bundles, TRUE))) {
      return FALSE;
    }
    $access = $this->entityTypes->getAccessControlHandler($target->getEntityTypeId());
    $access->resetCache();
    if (!$target->access('view') || !$target->access('update')) {
      return FALSE;
    }
    // The same self context is used when the executor applies components.
    return $template->withExecutionContexts(['self' => new Context(EntityContextDefinition::fromEntity($target), $target)], fn() => $template->applies());
  }

  /**
   * {@inheritdoc}
   */
  protected function initialTarget(array &$selection): ?TypedDataInterface {
    $target = $this->getContextValue('target');
    if (!$target instanceof FieldableEntityInterface || $target->isNew()) {
      throw new \DomainException('Apply-to requires an existing fieldable entity.');
    }
    $selection['target'] = $this->identity($target);
    $selection['original'] = $this->fingerprint($target);
    $this->checkTarget($target, $selection);
    // Clone the entity, not the navigated property, to avoid write-through to
    // its source context or a computed/read-only entity reference property.
    return (clone $target)->getTypedData();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkTarget(FieldableEntityInterface $entity, array $selection): void {
    $identity = $selection['target'];
    $mapped = $this->getContextValue('target');
    if ($entity->isNew() || $this->identity($entity) !== $identity || !$mapped instanceof FieldableEntityInterface || $this->identity($mapped) !== $identity) {
      throw new ChecklistAttemptConflictException('The template target identity changed.');
    }
    $current = $this->entityTypes->getStorage($entity->getEntityTypeId())->loadUnchanged($entity->id());
    $language = $entity->language()->getId();
    if (!$current instanceof FieldableEntityInterface || !$current->hasTranslation($language)) {
      throw new ChecklistAttemptConflictException('The template target is no longer available.');
    }
    $current = $current->getTranslation($language);
    if ($this->identity($current) !== $identity || $this->fingerprint($current) !== $selection['original']) {
      throw new ChecklistAttemptConflictException('The template target changed during preparation or editing.');
    }
    $access = $this->entityTypes->getAccessControlHandler($entity->getEntityTypeId());
    $access->resetCache();
    if (!$current->access('view') || !$current->access('update') || !$entity->access('update')) {
      throw new AccessDeniedHttpException('The template target cannot be updated.');
    }
  }

  /**
   * Pins identity, translation and revision independently of editable fields.
   */
  protected function identity(FieldableEntityInterface $entity): array {
    return [
      $entity->getEntityTypeId(), (string) $entity->id(), $entity->uuid(),
      $entity->bundle(), $entity->language()->getId(),
      $entity->getEntityType()->isRevisionable() ? (string) $entity->getRevisionId() : NULL,
    ];
  }

  /**
   * Detects intervening saves without retaining another copy of entity values.
   *
   * This is a stale-data check, not an atomic storage compare-and-swap. Entity
   * storage owns concurrency with writers outside the checklist's item claim.
   */
  protected function fingerprint(FieldableEntityInterface $entity): string {
    $values = [];
    foreach ($entity->getTranslationLanguages() as $language) {
      $values[$language->getId()] = $entity->getTranslation($language->getId())->toArray();
    }
    ksort($values);
    return hash('sha256', serialize($values));
  }

  /**
   * {@inheritdoc}
   */
  public function expectedOutcomeDefinitions(): array {
    $definitions = parent::expectedOutcomeDefinitions();
    $definitions['entity']->setLabel($this->t('Updated entity'));
    return $definitions;
  }

}
