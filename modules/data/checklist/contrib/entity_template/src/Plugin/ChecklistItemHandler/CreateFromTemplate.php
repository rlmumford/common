<?php

namespace Drupal\checklist_entity_template\Plugin\ChecklistItemHandler;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Plugin\ChecklistItemHandler\InteractiveChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ActionOperationsChecklistItemHandlerInterface;
use Drupal\checklist_entity_template\TemplateEditor;
use Drupal\flexiform\Session\FormSession;
use Drupal\checklist\ChecklistActionState;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Execution\ChecklistItemExecutor;
use Drupal\checklist\Execution\ChecklistItemResult;
use Drupal\checklist\Plugin\ChecklistItemHandler\ActionStateChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ContextAwareChecklistItemHandlerBase;
use Drupal\checklist\Plugin\ChecklistItemHandler\ExpectedOutcomeChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\IterativeChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\StatefulChecklistItemHandlerInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Serialization\PhpSerialize;
use Drupal\Core\Condition\ConditionManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\entity_template\BlueprintResult;
use Drupal\entity_template\Execution\ExecutionContext;
use Drupal\entity_template\Plugin\EntityTemplate\Template\BlueprintTemplateInterface;
use Drupal\entity_template\Plugin\EntityTemplate\Template\Template;
use Drupal\entity_template\TemplateManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
 *     "action" = "\Drupal\checklist_entity_template\PluginForm\TemplateEditorActionForm",
 *   }
 * )
 */
class CreateFromTemplate extends ContextAwareChecklistItemHandlerBase implements IterativeChecklistItemHandlerInterface, StatefulChecklistItemHandlerInterface, ExpectedOutcomeChecklistItemHandlerInterface, ActionStateChecklistItemHandlerInterface, DependentPluginInterface, InteractiveChecklistItemHandlerInterface, ActionOperationsChecklistItemHandlerInterface {

  /**
   * The template plugin manager.
   */
  protected TemplateManager $templates;

  /**
   * Entity storage and access handlers.
   */
  protected EntityTypeManagerInterface $entityTypes;

  /**
   * The audited automatic execution entry point.
   */
  protected ChecklistItemExecutor $itemExecutor;

  /**
   * Records diagnostics separately from public progress and attempt history.
   */
  protected LoggerInterface $logger;

  /**
   * Condition plugins contributing configuration dependencies.
   */
  protected ConditionManager $conditions;

  /**
   * Shared interactive coordinator.
   */
  protected TemplateEditor $editor;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->templates = $container->get('plugin.manager.entity_template.template');
    $instance->entityTypes = $container->get('entity_type.manager');
    $instance->itemExecutor = $container->get('checklist.item_executor');
    $instance->logger = $container->get('logger.channel.checklist');
    $instance->conditions = $container->get('plugin.manager.condition');
    $instance->editor = $container->get('checklist_entity_template.editor');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['editor' => [], 'blueprint' => '', 'template_id' => '', 'template' => [], 'context_mapping' => []] + parent::defaultConfiguration();
  }

  /**
   * Resolves configuration without executing components or creating a target.
   */
  public function getTemplate(): Template {
    $configuration = $this->getConfiguration();
    if ($configuration['blueprint'] !== '') {
      $blueprint = $this->entityTypes->getStorage('entity_template_blueprint')->load($configuration['blueprint']);
      if (!$blueprint) {
        throw new \InvalidArgumentException('The configured template blueprint does not exist.');
      }
      $template = $blueprint->toBlueprint()->getTemplate($configuration['template_id']);
    }
    else {
      $settings = $configuration['template'];
      $template = $this->templates->createInstance($settings['id'] ?? 'standalone', $settings);
    }
    if (!$template instanceof Template || !$template->isSingle()) {
      throw new \InvalidArgumentException('This checklist item requires a template returning one entity.');
    }
    return $template;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinitions() {
    $definitions = $this->getTemplate()->getContextDefinitions();
    unset($definitions['_blueprint_result']);
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinition($name) {
    $definitions = $this->getContextDefinitions();
    if (!isset($definitions[$name])) {
      throw new ContextException('The requested template parameter does not exist.');
    }
    return $definitions[$name];
  }

  /**
   * {@inheritdoc}
   */
  public function getMethod(): string {
    return $this->getConfiguration()['editor'] ? ChecklistItemInterface::METHOD_INTERACTIVE : ChecklistItemInterface::METHOD_AUTO;
  }

  /**
   * {@inheritdoc}
   */
  public function hasFormClass($operation) {
    return ($operation !== 'action' || $this->getMethod() === ChecklistItemInterface::METHOD_INTERACTIVE) && parent::hasFormClass($operation);
  }

  /**
   * {@inheritdoc}
   */
  public function action(): ChecklistItemHandlerInterface {
    if ($this->getMethod() === ChecklistItemInterface::METHOD_AUTO) {
      $this->itemExecutor->submit($this->getItem());
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function stateDefinitions(): array {
    // A structured value uses typed_data_reference's blob storage. The opaque
    // snapshot belongs to server code, never to submitted API parameters.
    return [
      'editor' => MapDataDefinition::create()
        ->setLabel(new TranslatableMarkup('Shared editor'))
        ->setPropertyDefinition('snapshot', DataDefinition::create('string')),
      'preparation' => MapDataDefinition::create()
        ->setLabel(new TranslatableMarkup('Template preparation'))
        ->setPropertyDefinition('snapshot', DataDefinition::create('string')),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function expectedOutcomeDefinitions(): array {
    $definition = clone $this->getTemplate()->getTargetDefinition();
    $definition->setLabel(new TranslatableMarkup('Created entity'));
    return ['entity' => $definition];
  }

  /**
   * Returns the captured template and execution, or initializes a new pass.
   */
  protected function execution(): array {
    $stored = $this->getItem()->get('state')->get('preparation')->getValue();
    if (!empty($stored['snapshot'])) {
      [$template, $execution, $editable] = PhpSerialize::decode($stored['snapshot']);
      if (!$template instanceof Template || !$execution instanceof ExecutionContext) {
        throw new \UnexpectedValueException('Invalid retained template preparation.');
      }
      return [$template, $execution, $editable];
    }
    $template = $this->getTemplate();
    foreach ($this->getContexts() as $name => $context) {
      if ($context->hasContextValue()) {
        $template->setContext($name, $context);
      }
    }
    if ($template instanceof BlueprintTemplateInterface) {
      $template->setBlueprintResult(new BlueprintResult());
    }
    return [$template, new ExecutionContext(2000), []];
  }

  /**
   * {@inheritdoc}
   */
  public function actionIteration(ChecklistAttempt $attempt): ChecklistItemResult {
    [$template, $execution, $editable] = $this->execution();
    $entity = NULL;
    try {
      $partial = $execution->getState()?->target->getValue();
      if ($partial instanceof FieldableEntityInterface) {
        $this->checkFieldAccess($partial, $editable);
      }
      $result = $template->executeTemplate(NULL, $execution);
      $entity = $result->getEntity() ?? $execution->getState()?->target->getValue();
      if ($entity instanceof FieldableEntityInterface) {
        foreach ($entity->getFields() as $name => $field) {
          if ($field->access('edit')) {
            $editable[$name] = $name;
          }
        }
      }
      if ($result->isPending()) {
        return new ChecklistItemResult(ChecklistAttempt::WAITING, $this->workingState($template, $execution, $editable, $entity), delay: 1, reason: 'Template preparation is pending.');
      }
      $entity = $result->getEntity();
      if (!$result->isComplete() || !$entity instanceof FieldableEntityInterface || !$entity->isNew()) {
        throw new \UnexpectedValueException('The template did not prepare a new fieldable entity.');
      }
      if (!$this->getConfiguration()['editor'] && $entity->validate()->count()) {
        throw new \UnexpectedValueException('The prepared entity is invalid.');
      }
      if ($this->getConfiguration()['editor']) {
        $session = $this->editor->createSession($this->getConfiguration()['editor'], $entity);
        return $this->editorResult($session, $editable);
      }
    }
    catch (\Throwable $exception) {
      $this->logger->error('Checklist template preparation failed: @message', ['@message' => $exception->getMessage()]);
      return new ChecklistItemResult(ChecklistAttempt::FAILED, $this->workingState($template, $execution, $editable, $entity), reason: 'Template preparation failed.');
    }
    return $this->completedResult($entity, $editable);
  }

  /**
   * Returns the private shared session and retained field-access requirements.
   */
  public function getEditorSession(): ?array {
    $stored = $this->getItem()->get('state')->get('editor')->getValue();
    return empty($stored['snapshot']) ? NULL : PhpSerialize::decode($stored['snapshot']);
  }

  /**
   * Builds a ready/wizard result without saving provider-owned data.
   */
  public function editorResult(FormSession $session, array $editable, bool $complete = FALSE): ChecklistItemResult {
    $entity = $session->getDataManager()->getContext('entity')->getContextValue();
    if ($complete) {
      return $this->completedResult($entity, $editable);
    }
    return new ChecklistItemResult(ChecklistAttempt::WAITING, [
      'preparation' => NULL,
      'editor' => ['snapshot' => PhpSerialize::encode([$session, $editable])],
    ], reason: 'Waiting for form input.');
  }

  /**
   * Saves only the prepared entity after the coordinator rechecks its claim.
   */
  protected function completedResult(FieldableEntityInterface $entity, array $editable): ChecklistItemResult {
    return new ChecklistItemResult(ChecklistAttempt::SUCCEEDED, outcomes: ['entity' => $entity], persist: function () use ($entity, $editable): void {
      if (!$entity->isNew()) {
        throw new \UnexpectedValueException('The template result must remain a new entity until completion.');
      }
      $access = $this->entityTypes->getAccessControlHandler($entity->getEntityTypeId());
      $access->resetCache();
      if (!$access->createAccess($entity->bundle())) {
        throw new AccessDeniedHttpException('The template result cannot be created.');
      }
      $this->checkFieldAccess($entity, $editable);
      if ($entity->validate()->count()) {
        throw new \UnexpectedValueException('The prepared entity is no longer valid.');
      }
      $entity->save();
    });
  }

  /**
   * Serializes trusted preparation state into the declared blob-backed map.
   */
  protected function workingState(Template $template, ExecutionContext $execution, array $editable, ?FieldableEntityInterface $entity): array {
    // On final validation failure the executor has already cleared its state;
    // retain the unsaved result as well for diagnostics and explicit recovery.
    return ['preparation' => ['snapshot' => PhpSerialize::encode([$template, $execution, $editable, $entity])]];
  }

  /**
   * {@inheritdoc}
   */
  public function getActionState(): ?ChecklistActionState {
    if ($this->getItem()->isComplete()) {
      return new ChecklistActionState('complete', (string) $this->t('Entity created.'));
    }
    if ($this->getItem()->isFailed()) {
      return new ChecklistActionState('failed', (string) $this->t('Template preparation failed.'));
    }
    if ($this->getEditorSession()) {
      return new ChecklistActionState('editing', (string) $this->t('Review the prepared entity.'), inputRequired: TRUE);
    }
    return new ChecklistActionState('preparing', (string) $this->t('Preparing the entity.'));
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $template = $this->getTemplate();
    $modules = ['entity_template', 'checklist_entity_template', $template->getPluginDefinition()['provider']];
    $configs = [];
    if ($this->getConfiguration()['blueprint'] !== '') {
      $blueprint = $this->entityTypes->getStorage('entity_template_blueprint')->load($this->getConfiguration()['blueprint']);
      $configs[] = $blueprint->getConfigDependencyName();
      $builder = $blueprint->toBlueprint()->getBuilder();
      $modules[] = $builder->getPluginDefinition()['provider'];
      if ($builder->getBaseId() === 'config') {
        $configs[] = 'entity_template.builder.' . $builder->getDerivativeId();
      }
    }
    $definition = $template->getTargetDefinition();
    $type = $this->entityTypes->getDefinition($definition->getEntityTypeId());
    $modules[] = $type->getProvider();
    if ($bundle_type = $type->getBundleEntityType()) {
      $bundles = (array) $definition->getConstraint('Bundle');
      foreach ($this->entityTypes->getStorage($bundle_type)->loadMultiple($bundles) as $bundle) {
        $configs[] = $bundle->getConfigDependencyName();
      }
    }
    $plugins = iterator_to_array($template->getComponents());
    $conditions = array_values($template->getConfiguration()['conditions'] ?? []);
    foreach ($plugins as $component) {
      $conditions = array_merge($conditions, array_values($component->getConfiguration()['conditions'] ?? []));
    }
    foreach ($conditions as $condition) {
      $plugins[] = $this->conditions->createInstance($condition['id'], $condition);
    }
    foreach ($plugins as $plugin) {
      $modules[] = $plugin->getPluginDefinition()['provider'];
      if ($plugin instanceof DependentPluginInterface) {
        $dependencies = $plugin->calculateDependencies();
        $modules = array_merge($modules, $dependencies['module'] ?? []);
        $configs = array_merge($configs, $dependencies['config'] ?? []);
      }
    }
    if ($settings = $this->getConfiguration()['editor']) {
      $dependencies = $this->editor->form($settings)->calculateDependencies();
      $modules = array_merge($modules, $dependencies['module'] ?? []);
      $configs = array_merge($configs, $dependencies['config'] ?? []);
      if (!empty($settings['form_id'])) {
        $configs[] = 'flexiform.form.' . $settings['form_id'];
      }
    }
    return ['module' => array_values(array_unique($modules)), 'config' => array_values(array_unique($configs))];
  }

  /**
   * Rejects loss of field access across preparation requests or before saving.
   *
   * The template engine checks actual writes in each pass. Keep the fields it
   * was allowed to edit so a later permission loss also blocks earlier edits.
   * Fields that were never editable can still retain their legitimate defaults.
   */
  protected function checkFieldAccess(FieldableEntityInterface $entity, array $editable): void {
    foreach ($editable as $name) {
      if (!$entity->hasField($name) || !$entity->get($name)->access('edit')) {
        throw new AccessDeniedHttpException('Access to a prepared entity field has changed.');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function actionOperations(): array {
    return $this->getConfiguration()['editor'] ? $this->editor->operations($this->getItem()) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function executeActionOperation(string $operation, array $parameters): array {
    return $this->editor->operate($this->getItem(), $operation, $parameters);
  }

}
