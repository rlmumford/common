<?php

namespace Drupal\task_job\Plugin\ChecklistItemHandler;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\ChecklistConditionEvaluator;
use Drupal\checklist\ChecklistContextMapping;
use Drupal\checklist\Execution\ChecklistItemResult;
use Drupal\checklist\Plugin\ChecklistItemHandler\AutomaticChecklistItemHandlerBase;
use Drupal\checklist\Plugin\ChecklistItemHandler\ExpectedOutcomeChecklistItemHandlerInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\task_job\JobTemplateCollection;
use Drupal\task_job\JobChecklistExpansion;
use Drupal\task_job\JobExecutionAuthorization;
use Drupal\task_job\JobInterface;
use Drupal\task_job\Plugin\ChecklistType\Job;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Activates one scoped template instance through the audited item executor.
 *
 * @ChecklistItemHandler(
 *   id = "add_checklist_template",
 *   label = @Translation("Add checklist template"),
 *   category = @Translation("Task job"),
 *   forms = {
 *     "configure" = "\Drupal\task_job\PluginForm\AddChecklistTemplateConfigureForm"
 *   }
 * )
 */
class AddChecklistTemplate extends AutomaticChecklistItemHandlerBase implements ExpectedOutcomeChecklistItemHandlerInterface {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, ChecklistConditionEvaluator $conditions, protected JobExecutionAuthorization $authorization, protected EntityStorageInterface $itemStorage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $conditions);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('checklist.condition_evaluator'), $container->get('task_job.execution_authorization'), $container->get('entity_type.manager')->getStorage('checklist_item'));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['template' => '', 'context_mapping' => [], 'collection_input' => ''] + parent::defaultConfiguration();
  }

  /**
   * Requires a live job checklist with authoritative template definitions.
   */
  protected function job(): JobInterface {
    $reference = $this->getItem()->get('checklist');
    [$field, $delta] = array_pad(explode(':', $reference->checklist_key, 2), 2, 0);
    $type = $reference->entity->get($field)->get($delta)->plugin;
    if (!$type instanceof Job || isset($type->getConfiguration()['default_items']) || !$job = $type->getJob()) {
      throw new \DomainException('Template expansion requires a live task job checklist.');
    }
    return $job;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinitions() {
    if (!$this->item) {
      return [];
    }
    $template = $this->getConfiguration()['template'];
    $templates = $this->job()->get('checklist_templates') ?: [];
    if (!isset($templates[$template])) {
      throw new \InvalidArgumentException('Select an existing checklist template.');
    }
    $definitions = ChecklistContextMapping::definitions($templates[$template]['context'] ?? []);
    if ($input = $this->getConfiguration()['collection_input']) {
      if (!isset($definitions[$input]) || $definitions[$input]->isMultiple()) {
        throw new \InvalidArgumentException('Repeat for each requires a single-valued template input.');
      }
      $definitions[$input]->setMultiple(TRUE);
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function expectedOutcomeDefinitions(): array {
    $definitions = ['template' => DataDefinition::create('string')->setLabel($this->t('Activated checklist template'))];
    if ($input = $this->getConfiguration()['collection_input']) {
      $definitions['members'] = $this->getContextDefinitions()[$input]->setRequired(FALSE)->getDataDefinition();
      $definitions['members']->setLabel($this->t('Collection members'))->setRequired(FALSE);
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary(): array {
    return ['#plain_text' => $this->getConfiguration()['template']];
  }

  /**
   * {@inheritdoc}
   */
  public function actionIteration(ChecklistAttempt $attempt): ChecklistItemResult {
    $job = $this->job();
    $configuration = $this->getConfiguration();
    $definitions = JobChecklistExpansion::instance($configuration['template'], $job->get('checklist_templates') ?: [], '', $configuration['context_mapping']);
    foreach ($definitions as $definition) {
      if (($definition['execution']['mode'] ?? 'self') === 'context') {
        $this->authorization->approvedGrant($job);
        break;
      }
    }
    $members = NULL;
    if ($input = $configuration['collection_input']) {
      $members = array_values($this->getContextValue($input));
      if (count($members) > 1000) {
        throw new \InvalidArgumentException('A collection cannot exceed 1,000 members.');
      }
      foreach ($members as $member) {
        if ($member instanceof EntityInterface && ($member->isNew() || !$member->access('view'))) {
          throw new \DomainException('Collection entities must be saved and accessible.');
        }
      }
    }
    // Completion and the selected-template outcome commit together. Stable
    // derived names activate the existing definitions without duplicating work.
    return new ChecklistItemResult(
      ChecklistAttempt::SUCCEEDED,
      outcomes: ['template' => $configuration['template']] + ($members === NULL ? [] : ['members' => $members]),
      reason: 'Checklist template activated.',
      persist: $members === NULL ? NULL : function () use ($configuration, $members, $job): void {
        $parent = $this->getItem();
        $reference = $parent->get('checklist');
        [$field, $delta] = array_pad(explode(':', $reference->checklist_key, 2), 2, 0);
        $type = $reference->entity->get($field)->get($delta)->plugin;
        $definitions = $type->getItemDefinitions($reference->entity, key: $reference->checklist_key);
        $expanded = JobTemplateCollection::expand($definitions, $job->get('checklist_templates') ?: [], [
          $parent->getName() => ['configuration' => $configuration, 'count' => count($members)],
        ]);
        foreach (array_diff_key($expanded, $definitions) as $name => $definition) {
          $this->itemStorage->create([
            'checklist_type' => $parent->bundle(),
            'name' => $name,
            'title' => $definition['label'],
            'checklist' => $reference->getValue(),
            'derivation' => $definition['derivation'],
            'handler' => ['id' => $definition['handler'], 'configuration' => $definition['handler_configuration']],
          ])->save();
        }
      },
    );
  }

}
