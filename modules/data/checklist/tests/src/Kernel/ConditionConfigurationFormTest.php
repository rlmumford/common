<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests nested condition authoring independently of a particular host UI.
 *
 * @group checklist
 */
class ConditionConfigurationFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'options',
    'entity',
    'checklist',
    'plugin_reference',
    'typed_data',
    'typed_data_plus',
    'typed_data_reference',
    'typed_data_context_assignment',
    'inline_entity_form',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
  }

  /**
   * Builds and validates a condition inside an unrelated containing form.
   */
  protected function configure(array $configuration, array $values): array {
    $state = (new FormState())->setValues(['settings' => ['gate' => $values]]);
    $form = ['#parents' => [], '#array_parents' => []];
    $element = ['#parents' => ['settings', 'gate']];
    $substate = SubformState::createForSubform($element, $form, $state);
    $editor = $this->container->get('checklist.condition_configuration_form');
    $contexts = ['source' => new Context(new ContextDefinition('string', 'Earlier outcome', FALSE))];
    $element = $editor->build($element, $substate, $configuration, $contexts);
    $this->parents($element, ['settings', 'gate']);
    $substate = SubformState::createForSubform($element, $form, $state);
    return [$editor->configuration($element, $substate), $state];
  }

  /**
   * Supplies the array paths normally assigned during Form API processing.
   */
  protected function parents(array &$element, array $parents): void {
    $element['#array_parents'] = $parents;
    $element['#parents'] ??= $parents;
    foreach ($element as $key => &$child) {
      if (is_array($child) && !str_starts_with((string) $key, '#')) {
        $this->parents($child, [...$parents, $key]);
      }
    }
    unset($child);
  }

  /**
   * Nested groups retain negation and validate strings against outcome types.
   */
  public function testGroupAndString(): void {
    $configuration = [
      'id' => 'condition_xor',
      'conditions' => [
        ['id' => 'condition_string', 'condition_string' => 'source == "yes"'],
        ['id' => 'condition_constant:false'],
      ],
    ];
    [$result, $state] = $this->configure($configuration, [
      'id' => 'condition_xor',
      'settings' => ['negate' => 1],
      'children' => [
        ['id' => 'condition_string', 'settings' => ['condition_string' => 'source == "yes"', 'negate' => 0]],
        ['id' => 'condition_constant:false', 'settings' => ['negate' => 0]],
      ],
    ]);
    $this->assertFalse($state->hasAnyErrors());
    $this->assertTrue((bool) $result['negate']);
    $this->assertSame('source == "yes"', $result['conditions'][0]['condition_string']);
    $plugin = $this->container->get('plugin.manager.condition')->createInstance($result['id'], $result);
    $plugin->setExpectedContexts(['source' => new ContextDefinition('string', 'Earlier outcome', FALSE)]);
    $plugin->setRuntimeContexts(['source' => new Context(new ContextDefinition('string'), 'yes')]);
    $this->assertFalse($plugin->execute());
  }

  /**
   * A missing operand cannot silently change a binary condition's meaning.
   */
  public function testBinaryOperands(): void {
    [, $state] = $this->configure(['id' => 'condition_xor'], [
      'id' => 'condition_xor',
      'settings' => [],
      'children' => [
        ['id' => '', 'settings' => []],
        ['id' => '', 'settings' => []],
      ],
    ]);
    $this->assertTrue($state->hasAnyErrors());
  }

  /**
   * Unknown selectors are rejected using definitions, without runtime values.
   */
  public function testInvalidString(): void {
    [, $state] = $this->configure(['id' => 'condition_string'], [
      'id' => 'condition_string',
      'settings' => ['condition_string' => 'missing == "yes"'],
    ]);
    $this->assertTrue($state->hasAnyErrors());
  }

  /**
   * Caller sources are fixed; actual plugin inputs use the enhanced widget.
   */
  public function testFixedSourcesAndMappingWidget(): void {
    $editor = $this->container->get('checklist.condition_configuration_form');
    $state = new FormState();
    $contexts = ['source' => new Context(new ContextDefinition('string', 'Source', FALSE), 'yes')];
    $element = $editor->build(['#parents' => ['gate']], $state, [
      'id' => 'condition_string',
      'condition_string' => 'source == "yes"',
      'context_mapping' => ['source' => 'other'],
    ], $contexts);
    $this->assertArrayNotHasKey('source', $element['settings']['context_mapping']);
    $this->assertArrayHasKey('condition_string', $element['settings']);
    $element = $editor->build(['#parents' => ['gate']], $state, [
      'id' => 'user_role',
      'context_mapping' => ['user' => '@user.current_user_context:current_user'],
    ], $contexts);
    $input = $element['settings']['context_mapping']['user'];
    $this->assertSame('textfield', $input['#type']);
    $this->assertSame('typed_data_context_assignment.data_select_autocomplete', $input['#autocomplete_route_name']);
    $this->assertSame('@user.current_user_context:current_user', $input['#default_value']);
  }

  /**
   * Saved mappings cannot redirect caller-supplied contexts inside groups.
   */
  public function testNestedFixedContexts(): void {
    $contexts = [
      'source' => new Context(new ContextDefinition('string', 'Source', FALSE), 'yes'),
      'other' => new Context(new ContextDefinition('string', 'Other', FALSE), 'no'),
    ];
    $condition = $this->container->get('checklist.condition_evaluator')->createCondition([
      'id' => 'condition_and',
      'context_mapping' => ['source' => 'other'],
      'conditions' => [
        [
          'id' => 'condition_string',
          'condition_string' => 'source == "yes"',
          'context_mapping' => ['source' => 'other'],
        ],
      ],
    ], $contexts);
    $condition->setRuntimeContexts($contexts);
    $this->assertTrue($condition->execute());
    $this->assertArrayNotHasKey('source', $condition->getContextMapping());
    $this->assertArrayNotHasKey('source', $condition->getConfiguration()['conditions'][0]['context_mapping']);
  }

}
