<?php

declare(strict_types=1);

namespace Drupal\Tests\typed_data_plus\Kernel;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\KernelTests\KernelTestBase;
use Drupal\typed_data_plus\Condition\ConditionException;

/**
 * Tests data conditions independently of Entity Template and checklist modules.
 *
 * @group typed_data_plus
 */
class ConditionEvaluatorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'typed_data', 'typed_data_plus'];

  /**
   * Tests nested conditions, filtered selectors and typed value references.
   */
  public function testDataPredicates(): void {
    $manager = $this->container->get('typed_data_manager');
    $contexts = [
      'name' => $manager->create(DataDefinition::create('string'), 'alpha and beta'),
      'count' => $manager->create(DataDefinition::create('integer'), 3),
      'limit' => $manager->create(DataDefinition::create('integer'), 2),
      'missing' => $manager->create(DataDefinition::create('string'), NULL),
      'choices' => $manager->create(ListDataDefinition::create('string'), ['alpha', 'beta']),
    ];
    $evaluator = $this->container->get('typed_data_plus.condition_evaluator');
    foreach ([
      '',
      "name|upper == 'ALPHA AND BETA'",
      'count > {{limit}} and (missing empty or NEVER)',
      'not (count < 2 or NEVER)',
      'missing not exists',
      "choices contains 'alpha' and name notcontains 'gamma'",
      "choices.0 in {{choices}}",
      "missing|default('a.b,c|d(e)') == 'a.b,c|d(e)'",
      'count exists and count notempty',
      implode(' and ', array_fill(0, 8, 'count exists')),
    ] as $expression) {
      $result = $evaluator->evaluate($expression, $contexts);
      $this->assertTrue($result->isMet(), $expression);
      $this->assertSame([], $result->getReasons());
    }
    foreach (['NEVER', 'count < 2', 'missing == {{missing}}', 'missing notempty'] as $expression) {
      $result = $evaluator->evaluate($expression, $contexts);
      $this->assertFalse($result->isMet(), $expression);
      $this->assertNotEmpty($result->getReasons());
    }
  }

  /**
   * Tests that fresh values are used on every call and errors cannot fail open.
   */
  public function testReevaluationAndUnknownBranches(): void {
    $data = $this->container->get('typed_data_manager')->create(DataDefinition::create('integer'), 1);
    $evaluator = $this->container->get('typed_data_plus.condition_evaluator');
    $this->assertFalse($evaluator->evaluate('value > 1', ['value' => $data])->isMet());
    $data->setValue(2);
    $this->assertTrue($evaluator->evaluate('value > 1', ['value' => $data])->isMet());
    $this->expectException(ConditionException::class);
    $evaluator->evaluate('value exists or typo exists', ['value' => $data]);
  }

  /**
   * Tests validation using definitions without runtime context values.
   */
  public function testExpectedDefinitions(): void {
    $evaluator = $this->container->get('typed_data_plus.condition_evaluator');
    $definitions = ['names' => ListDataDefinition::create('string')];
    $evaluator->validate("names.0|upper == 'FIRST'", $definitions);
    $this->addToAssertionCount(1);
    $this->expectException(ConditionException::class);
    $evaluator->validate('names.0.invalid exists', $definitions);
  }

  /**
   * Tests propagation of metadata collected while fetching a context.
   */
  public function testCacheMetadata(): void {
    $data = new CacheableConditionData(DataDefinition::create('string'));
    $data->setValue('example');
    $result = $this->container->get('typed_data_plus.condition_evaluator')->evaluate('value exists', ['value' => $data]);
    $this->assertContains('example:1', $result->getCacheTags());
    $this->assertContains('user.permissions', $result->getCacheContexts());
    $this->assertSame(30, $result->getCacheMaxAge());
  }

  /**
   * Tests that malformed and unsupported conditions never silently succeed.
   *
   * @dataProvider invalidConditions
   */
  public function testInvalidConditions(string $expression): void {
    $data = $this->container->get('typed_data_manager')->create(DataDefinition::create('string'), NULL);
    $this->expectException(ConditionException::class);
    $this->container->get('typed_data_plus.condition_evaluator')->evaluate($expression, ['value' => $data]);
  }

  /**
   * Supplies malformed syntax and unsupported module-specific predicates.
   */
  public static function invalidConditions(): array {
    return array_map(static fn($value) => [$value], [
      'value exists garbage', 'value exists and', 'and value exists',
      'value exists or NEVER and value empty', '(value exists', '()',
      'value exists) ignored', 'value == "unterminated', 'value == {{value}',
      'value exists or value complete', 'value', 'view_name view notempty',
      'value == {{missing}}', 'value == "a"junk', 'value|upper( exists',
      'value|missing_filter exists',
    ]);
  }

}
