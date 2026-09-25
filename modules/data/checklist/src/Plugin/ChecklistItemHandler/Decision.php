<?php

namespace Drupal\checklist\Plugin\ChecklistItemHandler;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\typed_data_plus\TypedData\StringEnumDefinition;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Records a named choice, optionally with a reason.
 *
 * @ChecklistItemHandler(
 *   id = "decision",
 *   label = @Translation("Decision"),
 *   category = @Translation("Basic"),
 *   forms = {
 *     "configure" = "\Drupal\checklist\PluginForm\DecisionItemConfigureForm",
 *     "row" = "\Drupal\checklist\PluginForm\StartableItemRowForm",
 *     "action" = "\Drupal\checklist\PluginForm\DecisionItemActionForm",
 *   }
 * )
 */
class Decision extends ChecklistItemHandlerBase implements InteractiveChecklistItemHandlerInterface, ExpectedOutcomeChecklistItemHandlerInterface, ActionOperationsChecklistItemHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['question' => '', 'presentation' => 'buttons', 'options' => []] + parent::defaultConfiguration();
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
    // A choice must be supplied through the form or choose operation.
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary(): array {
    return ['#plain_text' => $this->getConfiguration()['question']];
  }

  /**
   * {@inheritdoc}
   */
  public function expectedOutcomeDefinitions(): array {
    return [
      'decision' => StringEnumDefinition::create()
        ->setOptions(array_map(static fn(array $option) => $option['label'], $this->options()))
        ->setLabel(new TranslatableMarkup('Decision')),
      'reason' => DataDefinition::create('string')->setLabel(new TranslatableMarkup('Reason')),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return $this->conditionEvaluator->calculateDependencies(array_merge(
      array_values($this->getConfiguration()['conditions']),
      array_column($this->getConfiguration()['options'], 'available'),
    ));
  }

  /**
   * Returns configured options, rejecting ambiguous or malformed choices.
   */
  protected function options(): array {
    $options = $this->getConfiguration()['options'];
    if (!is_array($options) || !$options) {
      throw new \InvalidArgumentException('A decision requires named options.');
    }
    foreach ($options as $name => $option) {
      if (!is_string($name) || !preg_match('/^[a-z][a-z0-9_]*$/D', $name) || !is_array($option) || !is_string($option['label'] ?? NULL) || trim($option['label']) === '') {
        throw new \InvalidArgumentException('Decision options require machine names and non-empty labels.');
      }
      if (isset($option['require_reason']) && !is_bool($option['require_reason'])) {
        throw new \InvalidArgumentException('Decision require_reason must be boolean.');
      }
    }
    return $options;
  }

  /**
   * Checks whether the current caller can choose an outcome for this item.
   */
  protected function assertCanChoose(): void {
    $item = $this->getItem();
    if (!$item->get('checklist')->checklist->getEntity()->access('update')) {
      throw new AccessDeniedHttpException('The checklist cannot be updated.');
    }
    if (!$item->isIncomplete() || $item->isApplicable() !== TRUE || !$item->isActionable()) {
      throw new \DomainException('The decision is not actionable.');
    }
  }

  /**
   * Tests an option's condition against fresh checklist contexts.
   */
  protected function optionAvailable(array $option): bool {
    return !array_key_exists('available', $option) || $this->conditionEvaluator->evaluate(
      $this->getItem()->get('checklist')->checklist,
      $option['available']
    ) === TRUE;
  }

  /**
   * Returns currently permitted options, including their labels and metadata.
   */
  public function availableOptions(): array {
    try {
      $this->assertCanChoose();
    }
    catch (AccessDeniedHttpException | \DomainException) {
      return [];
    }
    return array_filter($this->options(), fn(array $option) => $this->optionAvailable($option));
  }

  /**
   * Validates without changing the item, for form validation and execution.
   */
  public function validateChoice(string $choice, string $reason = ''): void {
    $this->assertCanChoose();
    $options = $this->options();
    if (!isset($options[$choice]) || !$this->optionAvailable($options[$choice])) {
      throw new \InvalidArgumentException('Choose an available decision option.');
    }
    if (!empty($options[$choice]['require_reason']) && trim($reason) === '') {
      throw new \InvalidArgumentException('A reason is required for this decision.');
    }
  }

  /**
   * Applies a choice and saves its outcomes and completion together.
   */
  public function choose(string $choice, string $reason = ''): array {
    $this->validateChoice($choice, $reason);
    $result = ['decision' => $choice, 'reason' => trim($reason)];
    $item = $this->getItem();
    foreach ($result as $name => $value) {
      $item->setOutcome($name, $value);
    }
    $item->setComplete(ChecklistItemInterface::METHOD_INTERACTIVE)->save();
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function actionOperations(): array {
    $options = $this->availableOptions();
    if (!$options) {
      return [];
    }
    $reason_options = array_keys(array_filter($options, static fn(array $option) => !empty($option['require_reason'])));
    $operations = [
      'choose' => [
        'label' => (string) $this->t('Choose'),
        'description' => $this->getConfiguration()['question'],
        'parameters_schema' => [
          'type' => 'object',
          'properties' => [
            'choice' => ['type' => 'string', 'enum' => array_keys($options)],
            'reason' => ['type' => 'string', 'description' => 'Required when the chosen option requires a reason.'],
          ],
          'required' => ['choice'],
          'additionalProperties' => FALSE,
        ],
        'result_schema' => [
          'type' => 'object',
          'properties' => [
            'decision' => ['type' => 'string', 'enum' => array_keys($options)],
            'reason' => ['type' => 'string'],
          ],
          'required' => ['decision', 'reason'],
          'additionalProperties' => FALSE,
        ],
      ],
    ];
    if ($reason_options) {
      $operations['choose']['parameters_schema']['allOf'] = [
        [
          'if' => ['properties' => ['choice' => ['enum' => $reason_options]]],
          'then' => [
            'required' => ['reason'],
            'properties' => ['reason' => ['pattern' => '\\S']],
          ],
        ],
      ];
    }
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function executeActionOperation(string $operation, array $parameters): array {
    if ($operation !== 'choose') {
      throw new \InvalidArgumentException('Unknown decision operation.');
    }
    if (array_diff(array_keys($parameters), ['choice', 'reason']) || !is_string($parameters['choice'] ?? NULL) || (array_key_exists('reason', $parameters) && !is_string($parameters['reason']))) {
      throw new \InvalidArgumentException('Decision parameters require a choice string and an optional reason string.');
    }
    return $this->choose($parameters['choice'], $parameters['reason'] ?? '');
  }

}
