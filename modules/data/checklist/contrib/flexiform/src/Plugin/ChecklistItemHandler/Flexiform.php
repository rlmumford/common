<?php

namespace Drupal\checklist_flexiform\Plugin\ChecklistItemHandler;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\ChecklistActionState;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Execution\ChecklistItemResult;
use Drupal\checklist\Plugin\ChecklistItemHandler\ActionOperationsChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ActionStateChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ContextAwareChecklistItemHandlerBase;
use Drupal\checklist\Plugin\ChecklistItemHandler\ExpectedOutcomeChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\InteractiveChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\StatefulChecklistItemHandlerInterface;
use Drupal\checklist_flexiform\ChecklistFormEditor;
use Drupal\checklist_flexiform\FormData\ChecklistFormDataManager;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Serialization\PhpSerialize;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\flexiform\Api\ApiFormInterface;
use Drupal\flexiform\FormDataProviderManager;
use Drupal\flexiform\FormFactory;
use Drupal\flexiform\FormPluginInterface;
use Drupal\flexiform\Session\FormSession;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Collects structured input through a shared Flexiform session.
 *
 * @ChecklistItemHandler(
 *   id = "flexiform",
 *   label = @Translation("Flexiform"),
 *   category = @Translation("Forms"),
 *   forms = {
 *     "row" = "\Drupal\checklist\PluginForm\StartableItemRowForm",
 *     "action" = "\Drupal\checklist_flexiform\PluginForm\FormActionForm",
 *   }
 * )
 */
class Flexiform extends ContextAwareChecklistItemHandlerBase implements FormItemHandlerInterface, InteractiveChecklistItemHandlerInterface, StatefulChecklistItemHandlerInterface, ExpectedOutcomeChecklistItemHandlerInterface, ActionStateChecklistItemHandlerInterface, ActionOperationsChecklistItemHandlerInterface {

  /**
   * Builds referenced or embedded forms.
   */
  protected FormFactory $forms;
  /**
   * Creates the form data providers.
   */
  protected FormDataProviderManager $providers;
  /**
   * Resolves mapped form contexts.
   */
  protected ContextHandlerInterface $contextHandler;
  /**
   * Coordinates the shared form session.
   */
  protected ChecklistFormEditor $editor;
  /**
   * Records private preparation diagnostics.
   */
  protected LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->forms = $container->get('flexiform.form_factory');
    $instance->providers = $container->get('plugin.manager.flexiform.form_data_provider');
    $instance->contextHandler = $container->get('context.handler');
    $instance->editor = $container->get('checklist_flexiform.editor');
    $instance->logger = $container->get('logger.channel.checklist');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'form' => [
        'plugin' => 'standard',
        'configuration' => [],
      ],
      'context_mapping' => [],
      'outcomes' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * Resolves the reusable or embedded form without loading provider values.
   */
  public function form(): FormPluginInterface {
    $definition = $this->getConfiguration()['form'];
    $form = $this->forms->create($definition['configuration'], $definition['plugin']);
    if (!$form instanceof ApiFormInterface || $form->getFormEnhancers()) {
      throw new \InvalidArgumentException('Checklist forms require API-capable form plugins without submit enhancers. Use separate checklist items for additional effects.');
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinitions() {
    $form = $this->form();
    $definitions = [];
    foreach ($form->getDataConfig() as $name => $settings) {
      if (in_array($settings['plugin'], ['provided', 'provided_data'], TRUE)) {
        $definitions[$name] = (clone $form->getExpectedContexts()[$name])->setRequired(TRUE);
      }
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinition($name) {
    return $this->getContextDefinitions()[$name] ?? throw new ContextException('Unknown Flexiform input.');
  }

  /**
   * {@inheritdoc}
   */
  public function expectedOutcomeDefinitions(): array {
    $contexts = $this->form()->getExpectedContexts();
    $definitions = [];
    foreach ($this->getConfiguration()['outcomes'] as $name => $binding) {
      if (!preg_match('/^[a-z][a-z0-9_]*$/D', $name) || !isset($contexts[$binding])) {
        throw new \InvalidArgumentException('Outcomes require machine names and an existing form data binding.');
      }
      $definitions[$name] = clone $contexts[$binding]->getDataDefinition();
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function stateDefinitions(): array {
    return [
      'editor' => MapDataDefinition::create()->setPropertyDefinition('snapshot',
       DataDefinition::create('string')),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMethod(): string {
    return ChecklistItemInterface::METHOD_INTERACTIVE;
  }

  /**
   * {@inheritdoc}
   */
  public function action(): ChecklistItemHandlerInterface {
    throw new \LogicException('Use the shared Flexiform action form or operations.');
  }

  /**
   * {@inheritdoc}
   */
  public function editorChoices(): ?array {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function selectEditorChoice(string $key): void {
    throw new \InvalidArgumentException('A Flexiform item has no template choice.');
  }

  /**
   * {@inheritdoc}
   */
  public function getEditorSession(): ?array {
    $snapshot = $this->getItem()->get('state')->get('editor')->getValue()['snapshot'] ?? NULL;
    $editor = $snapshot ? PhpSerialize::decode($snapshot) : NULL;
    if ($editor && $editor[0]->describe()['status'] === 'ready') {
      $editor[0]->getDataManager()->checkAccess();
    }
    return $editor;
  }

  /**
   * {@inheritdoc}
   */
  public function actionIteration(ChecklistAttempt $attempt): ChecklistItemResult {
    $editor = $this->getEditorSession();
    if (!$editor) {
      $form = $this->form();
      $provided = [];
      foreach ($this->getContextDefinitions() as $name => $definition) {
        $provided[$name] = $form->getDataConfig()[$name]['plugin'] === 'provided'
          ? $this->getContextValue($name) : $this->getContext($name)->getContextData();
      }
      // Detach caller-owned entities before accepting or validating any input.
      $provided = PhpSerialize::decode(PhpSerialize::encode($provided));
      $manager = new ChecklistFormDataManager($form, $this->providers, $this->contextHandler, $provided);
      $editor = [new FormSession($form, $manager), ['outcomes' => $this->getConfiguration()['outcomes']]];
    }
    [$session, $metadata] = $editor;
    try {
      if ($session->prepare() && empty($session->describe()['supported'])) {
        throw new \InvalidArgumentException($session->describe()['reason'] ?? 'The form contains components without API support.');
      }
      return $this->editorResult($session, $metadata);
    }
    catch (\Throwable $exception) {
      $this->logger->error('Checklist form preparation failed: @message', ['@message' => $exception->getMessage()]);
      return new ChecklistItemResult(ChecklistAttempt::FAILED, ['editor' => ['snapshot' => PhpSerialize::encode($editor)]], reason: 'Form preparation failed.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function editorResult(FormSession $session, array $metadata, bool $complete = FALSE): ChecklistItemResult {
    if (!$complete) {
      return new ChecklistItemResult(ChecklistAttempt::WAITING, [
        'editor' => [
          'snapshot' => PhpSerialize::encode([$session, $metadata]),
        ],
      ], reason: 'Waiting for form preparation or input.');
    }
    $manager = $session->getDataManager();
    $outcomes = [];
    foreach ($metadata['outcomes'] as $name => $binding) {
      $outcomes[$name] = $manager->getContext($binding)->getContextValue();
    }
    return new ChecklistItemResult(ChecklistAttempt::SUCCEEDED, outcomes: $outcomes, persist: static function () use ($manager): void {
      $manager->commit();
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getActionState(): ?ChecklistActionState {
    if ($this->getItem()->isComplete()) {
      return new ChecklistActionState('complete', (string) $this->t('Form completed.'));
    }
    if ($this->getItem()->isFailed()) {
      return new ChecklistActionState('failed', (string) $this->t('Form preparation or submission failed.'));
    }
    if ($this->getItem()->get('state')->isEmpty()) {
      return NULL;
    }
    return new ChecklistActionState('input', (string) $this->t('Complete the form.'), inputRequired: TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function actionOperations(): array {
    return $this->editor->operations($this->getItem());
  }

  /**
   * {@inheritdoc}
   */
  public function executeActionOperation(string $operation, array $parameters): array {
    return $this->editor->operate($this->getItem(), $operation, $parameters);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    foreach ($this->form()->calculateDependencies() as $type => $names) {
      $dependencies[$type] = array_values(array_unique(array_merge($dependencies[$type] ?? [], $names)));
    }
    $dependencies['module'][] = 'checklist_flexiform';
    return $dependencies;
  }

}
