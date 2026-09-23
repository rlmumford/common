<?php

namespace Drupal\checklist_entity_template\Plugin\ChecklistItemHandler;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Plugin\ChecklistItemHandler\InteractiveChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ActionOperationsChecklistItemHandlerInterface;
use Drupal\checklist_entity_template\PreparedEntityEditor;
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
use Drupal\checklist\ChecklistContextCollectorInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\entity_template\BlueprintResult;
use Drupal\entity_template\Execution\ExecutionContext;
use Drupal\entity_template\Plugin\EntityTemplate\Template\BlueprintTemplateInterface;
use Drupal\entity_template\Plugin\EntityTemplate\Template\Template;
use Drupal\entity_template\TemplateSource;
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
 *     "action" = "\Drupal\checklist_entity_template\PluginForm\PreparedEntityEditorActionForm",
 *   }
 * )
 */
class CreateFromTemplate extends ContextAwareChecklistItemHandlerBase implements IterativeChecklistItemHandlerInterface, StatefulChecklistItemHandlerInterface, ExpectedOutcomeChecklistItemHandlerInterface, ActionStateChecklistItemHandlerInterface, DependentPluginInterface, InteractiveChecklistItemHandlerInterface, ActionOperationsChecklistItemHandlerInterface {

  /**
   * Resolves reusable and embedded template sources.
   */
  protected TemplateSource $sources;

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
   * Provides the checklist contexts used by candidate mappings and conditions.
   */
  protected ChecklistContextCollectorInterface $collector;

  /**
   * Resolves per-candidate parameter selectors, including global providers.
   */
  protected ContextHandlerInterface $contextHandler;

  /**
   * Choice made for this invocation, before its audited result is committed.
   */
  protected ?string $selected = NULL;

  /**
   * Shared interactive coordinator.
   */
  protected PreparedEntityEditor $editor;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->sources = $container->get('entity_template.source');
    $instance->entityTypes = $container->get('entity_type.manager');
    $instance->itemExecutor = $container->get('checklist.item_executor');
    $instance->logger = $container->get('logger.channel.checklist');
    $instance->collector = $container->get('checklist.context_collector');
    $instance->contextHandler = $container->get('context.handler');
    $instance->editor = $container->get('checklist_entity_template.editor');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['templates' => []] + parent::defaultConfiguration();
  }

  /**
   * Resolves every candidate without executing components or creating targets.
   */
  public function getTemplates(): array {
    $templates = [];
    foreach ($this->getConfiguration()['templates'] as $key => $settings) {
      if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]*$/D', $key)) {
        throw new \InvalidArgumentException('Template candidates require machine-name keys.');
      }
      $template = $this->sources->resolve($settings['template']);
      if (!$template->isSingle() || !$template->getTargetDefinition() instanceof EntityDataDefinitionInterface) {
        throw new \InvalidArgumentException('Each checklist template must return one entity.');
      }
      $templates[$key] = $template;
    }
    if (!$templates) {
      throw new \InvalidArgumentException('Configure at least one template candidate.');
    }
    return $templates;
  }

  /**
   * Namespaces inputs so candidates can reuse names with different data types.
   *
   * Candidate inputs are optional here: only the chosen candidate's required
   * inputs gate execution. Its original definitions are checked in available().
   */
  public function getContextDefinitions() {
    $definitions = [];
    foreach ($this->getTemplates() as $key => $template) {
      foreach ($template->getContextDefinitions() as $name => $definition) {
        if ($name !== '_blueprint_result') {
          $definitions[$key . '/' . $name] = (clone $definition)->setRequired(FALSE);
        }
      }
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextMapping() {
    $mapping = [];
    foreach ($this->getConfiguration()['templates'] as $key => $settings) {
      foreach ($settings['context_mapping'] ?? [] as $name => $selector) {
        $mapping[$key . '/' . $name] = $selector;
      }
    }
    return $mapping;
  }

  /**
   * Returns currently applicable candidates with independently mapped inputs.
   */
  public function available(): array {
    $checklist = $this->getItem()->get('checklist')->checklist;
    foreach ($this->getContextDefinitions() as $name => $definition) {
      $this->setContext($name, new Context($definition));
    }
    $this->contextHandler->applyContextMapping($this, $this->collector->collectRuntimeContexts($checklist));
    $available = [];
    foreach ($this->getTemplates() as $key => $template) {
      $settings = $this->getConfiguration()['templates'][$key];
      if (isset($settings['condition']) && $this->conditionEvaluator->evaluate($checklist, $settings['condition']) !== TRUE) {
        continue;
      }
      foreach ($template->getContextDefinitions() as $name => $definition) {
        if ($name === '_blueprint_result') {
          continue;
        }
        $context = $this->getContext($key . '/' . $name);
        if ($definition->isRequired() && !$context->hasContextValue()) {
          continue 2;
        }
        $template->setContext($name, $context);
      }
      if ($template instanceof BlueprintTemplateInterface) {
        $template->setBlueprintResult(new BlueprintResult());
      }
      if ($template->applies()) {
        $available[$key] = $template;
      }
    }
    return $available;
  }

  /**
   * Pins an explicitly chosen, currently available candidate for this pass.
   */
  public function select(string $key): void {
    if (!isset($this->available()[$key])) {
      throw new \DomainException('The chosen template is unavailable.');
    }
    $this->selected = $key;
  }

  /**
   * Reads the choice and editor captured when this attempt began.
   */
  protected function selection(): ?array {
    $stored = $this->getItem()->get('state')->get('selection')->getValue();
    return empty($stored['snapshot']) ? NULL : PhpSerialize::decode($stored['snapshot']);
  }

  /**
   * {@inheritdoc}
   */
  public function isActionable(): bool {
    return $this->selection() !== NULL ? parent::isActionable() : (bool) $this->available() && parent::isActionable();
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
    $selection = $this->selection();
    if ($selection) {
      return $selection['method'];
    }
    $available = $this->available();
    $key = array_key_first($available);
    return count($available) !== 1 || !empty($this->getConfiguration()['templates'][$key]['editor']) ? ChecklistItemInterface::METHOD_INTERACTIVE : ChecklistItemInterface::METHOD_AUTO;
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
      'selection' => MapDataDefinition::create()
        ->setLabel(new TranslatableMarkup('Selected template'))
        ->setPropertyDefinition('snapshot', DataDefinition::create('string')),
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
    $type = NULL;
    $bundles = [];
    $unrestricted = FALSE;
    foreach ($this->getTemplates() as $template) {
      $target = $template->getTargetDefinition();
      if ($type !== NULL && $type !== $target->getEntityTypeId()) {
        throw new \InvalidArgumentException('Template alternatives must produce the same entity type.');
      }
      $type = $target->getEntityTypeId();
      $bundle = $target->getConstraint('Bundle');
      $unrestricted = $unrestricted || !$bundle;
      $bundles = array_merge($bundles, (array) $bundle);
    }
    $definition = EntityDataDefinition::create($type)->setLabel(new TranslatableMarkup('Created entity'));
    if (!$unrestricted) {
      $definition->addConstraint('Bundle', array_values(array_unique($bundles)));
    }
    return ['entity' => $definition];
  }

  /**
   * Returns the captured template and execution, or initializes a new pass.
   */
  protected function execution(): array {
    $stored = $this->getItem()->get('state')->get('preparation')->getValue();
    if (!empty($stored['snapshot'])) {
      [$template, $execution, $editable, , $selection] = PhpSerialize::decode($stored['snapshot']);
      if (!$template instanceof Template || !$execution instanceof ExecutionContext) {
        throw new \UnexpectedValueException('Invalid retained template preparation.');
      }
      return [$template, $execution, $editable, $selection];
    }
    $available = $this->available();
    $key = $this->selected ?? (count($available) === 1 ? array_key_first($available) : NULL);
    if ($key === NULL || !isset($available[$key])) {
      throw new \DomainException('Choose an available template before execution.');
    }
    $editor = $this->getConfiguration()['templates'][$key]['editor'] ?? [];
    $editor = $editor ? $this->editor->form($editor) : NULL;
    $selection = [
      'key' => $key,
      'editor' => $editor,
      'method' => $this->getMethod(),
    ];
    return [$available[$key], new ExecutionContext(2000), [], $selection];
  }

  /**
   * {@inheritdoc}
   */
  public function actionIteration(ChecklistAttempt $attempt): ChecklistItemResult {
    [$template, $execution, $editable, $selection] = $this->execution();
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
        return new ChecklistItemResult(ChecklistAttempt::WAITING, $this->workingState($template, $execution, $editable, $entity, $selection), delay: 1, reason: 'Template preparation is pending.');
      }
      $entity = $result->getEntity();
      if (!$result->isComplete() || !$entity instanceof FieldableEntityInterface || !$entity->isNew()) {
        throw new \UnexpectedValueException('The template did not prepare a new fieldable entity.');
      }
      if (!$selection['editor'] && $entity->validate()->count()) {
        throw new \UnexpectedValueException('The prepared entity is invalid.');
      }
      if ($selection['editor']) {
        $session = $this->editor->createSession($selection['editor'], $entity);
        return $this->editorResult($session, $editable, FALSE, $selection);
      }
    }
    catch (\Throwable $exception) {
      $this->logger->error('Checklist template preparation failed: @message', ['@message' => $exception->getMessage()]);
      return new ChecklistItemResult(ChecklistAttempt::FAILED, $this->workingState($template, $execution, $editable, $entity, $selection), reason: 'Template preparation failed.');
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
  public function editorResult(FormSession $session, array $editable, bool $complete = FALSE, ?array $selection = NULL): ChecklistItemResult {
    $entity = $session->getDataManager()->getContext('entity')->getContextValue();
    if ($complete) {
      return $this->completedResult($entity, $editable);
    }
    return new ChecklistItemResult(ChecklistAttempt::WAITING, [
      'selection' => ['snapshot' => PhpSerialize::encode($selection ?? $this->selection())],
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
  protected function workingState(Template $template, ExecutionContext $execution, array $editable, ?FieldableEntityInterface $entity, array $selection): array {
    // On final validation failure the executor has already cleared its state;
    // retain the unsaved result as well for diagnostics and explicit recovery.
    return [
      'selection' => ['snapshot' => PhpSerialize::encode($selection)],
      'preparation' => ['snapshot' => PhpSerialize::encode([$template, $execution, $editable, $entity, $selection])],
    ];
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
    if ($this->selection() === NULL && count($this->available()) > 1) {
      return new ChecklistActionState('selecting', (string) $this->t('Choose a template.'), inputRequired: TRUE);
    }
    return new ChecklistActionState('preparing', (string) $this->t('Preparing the entity.'));
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = ['module' => ['checklist_entity_template'], 'config' => []];
    foreach ($this->getConfiguration()['templates'] as $settings) {
      $sources = [$this->sources->calculateDependencies($settings['template'])];
      if (!empty($settings['editor'])) {
        $sources[] = $this->editor->form($settings['editor'])->calculateDependencies();
      }
      if (isset($settings['condition'])) {
        $sources[] = $this->sources->conditionDependencies($settings['condition']);
      }
      foreach ($sources as $source) {
        foreach ($source as $type => $names) {
          $dependencies[$type] = array_merge($dependencies[$type] ?? [], $names);
        }
      }
    }
    return array_map(static fn($names) => array_values(array_unique($names)), $dependencies);
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
    return $this->getMethod() === ChecklistItemInterface::METHOD_INTERACTIVE ? $this->editor->operations($this->getItem()) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function executeActionOperation(string $operation, array $parameters): array {
    return $this->editor->operate($this->getItem(), $operation, $parameters);
  }

}
