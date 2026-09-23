<?php

namespace Drupal\checklist_entity_template\Plugin\ChecklistItemHandler;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Prepares one entity across audited iterations, then saves and publishes it.
 *
 * @ChecklistItemHandler(
 *   id = "entity_template__create",
 *   label = @Translation("Create entity from template"),
 *   category = @Translation("Entity Template"),
 *   forms = {
 *     "row" = "\Drupal\checklist_entity_template\PluginForm\TemplateItemRowForm",
 *     "action" = "\Drupal\checklist_entity_template\PluginForm\PreparedEntityEditorActionForm",
 *   }
 * )
 */
class CreateFromTemplate extends TemplateItemBase {

  /**
   * {@inheritdoc}
   */
  protected function initialTarget(array &$selection): ?TypedDataInterface {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkTarget(FieldableEntityInterface $entity, array $selection): void {
    if (!$entity->isNew()) {
      throw new \UnexpectedValueException('The template result must remain a new entity until completion.');
    }
    $access = $this->entityTypes->getAccessControlHandler($entity->getEntityTypeId());
    $access->resetCache();
    if (!$access->createAccess($entity->bundle())) {
      throw new AccessDeniedHttpException('The template result cannot be created.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function expectedOutcomeDefinitions(): array {
    $definitions = parent::expectedOutcomeDefinitions();
    $definitions['entity']->setLabel($this->t('Created entity'));
    return $definitions;
  }

}
