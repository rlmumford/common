<?php

namespace Drupal\checklist_communication\Plugin\ChecklistItemHandler;

use Drupal\checklist\Execution\ChecklistItemResult;
use Drupal\checklist_entity_template\Plugin\ChecklistItemHandler\CreateFromTemplate;
use Drupal\communication\Entity\CommunicationInterface;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Prepares a communication and atomically records its selected follow-up work.
 *
 * @ChecklistItemHandler(
 *   id = "entity_template__create_communication",
 *   label = @Translation("Create and operate on a communication"),
 *   category = @Translation("Communication"),
 *   forms = {
 *     "row" = "\Drupal\checklist_entity_template\PluginForm\TemplateItemRowForm",
 *     "action" = "\Drupal\checklist_entity_template\PluginForm\PreparedEntityEditorActionForm"
 *   }
 * )
 */
class CreateCommunication extends CreateFromTemplate {

  use OperationDependenciesTrait;

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies() + ['module' => []];
    foreach ($this->getConfiguration()['templates'] as $candidate) {
      $operation = $candidate['operation'];
      $dependencies['module'] = array_merge($dependencies['module'], $this->operationModules($operation['id'], $operation['variant'] ?? ''));
    }
    $dependencies['module'] = array_values(array_unique($dependencies['module']));
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function expectedOutcomeDefinitions(): array {
    foreach ($this->getTemplates() as $template) {
      if ($template->getTargetDefinition()->getEntityTypeId() !== 'communication') {
        throw new \InvalidArgumentException('Every template must produce a communication.');
      }
    }
    return parent::expectedOutcomeDefinitions();
  }

  /**
   * Captures the selected operation alongside the retained template and editor.
   */
  protected function execution(): array {
    $execution = parent::execution();
    if (!isset($execution[3]['operation'])) {
      $settings = $this->getConfiguration()['templates'][$execution[3]['key']]['operation'] ?? [];
      if (empty($settings['id'])) {
        throw new \InvalidArgumentException('Configure an operation for every communication template.');
      }
      $execution[3]['operation'] = $settings + ['variant' => '', 'confirm' => FALSE, 'label' => 'Send communication'];
    }
    return $execution;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkTarget(FieldableEntityInterface $entity, array $selection): void {
    if (!$entity instanceof CommunicationInterface) {
      throw new \UnexpectedValueException('The template must produce a communication.');
    }
    parent::checkTarget($entity, $selection);
  }

  /**
   * Saves the prepared entity and required child under the parent's claim.
   */
  protected function completedResult(FieldableEntityInterface $entity, array $editable, ?array $selection = NULL): ChecklistItemResult {
    $selection ??= $this->selection();
    $result = parent::completedResult($entity, $editable, $selection);
    $operation = $selection['operation'];
    return new ChecklistItemResult($result->status, outcomes: $result->outcomes, persist: function () use ($result, $operation): void {
      ($result->persist)();
      $parent = $this->getItem();
      $reference = $parent->get('checklist');
      $name = 'communication__' . str_replace('-', '', $parent->uuid());
      $storage = $this->entityTypes->getStorage('checklist_item');
      $existing = $storage->loadByProperties([
        'checklist_type' => $parent->bundle(),
        'name' => $name,
        'checklist.target_id' => $reference->target_id,
        'checklist.checklist_key' => $reference->checklist_key,
      ]);
      if ($existing) {
        throw new \DomainException('This communication already has operation work.');
      }
      // Claim fencing prevents duplicate parent commits. A deterministic child
      // name also rejects a configuration collision or duplicate save.
      $storage->create([
        'checklist_type' => $parent->bundle(),
        'name' => $name,
        'title' => $operation['label'],
        'checklist' => $reference->getValue(),
        'handler' => [
          'id' => 'communication_operation',
          'configuration' => [
            'operation' => $operation['id'],
            'variant' => $operation['variant'],
            'confirm' => $operation['confirm'],
            'context_mapping' => ['communication' => 'item:' . $parent->getName() . ':entity'],
          ],
        ],
      ])->save();
    });
  }

}
