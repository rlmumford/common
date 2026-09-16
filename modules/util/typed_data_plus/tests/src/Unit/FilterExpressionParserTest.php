<?php

declare(strict_types=1);

namespace Drupal\Tests\typed_data_plus\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\typed_data\Exception\InvalidArgumentException;
use Drupal\typed_data_plus\FilterExpressionParser;

/**
 * @coversDefaultClass \Drupal\typed_data_plus\FilterExpressionParser
 * @group typed_data_plus
 */
class FilterExpressionParserTest extends UnitTestCase {

  /**
   * Tests parsing of literal filter arguments.
   *
   * @dataProvider expressions
   */
  public function testParsing(string $expression, array $expected): void {
    $this->assertSame($expected, (new FilterExpressionParser())->parse($expression));
  }

  /**
   * Provides valid property paths and filter arguments.
   */
  public static function expressions(): array {
    return [
      ['', [[], []]],
      ['entity.field.0.value', [['entity', 'field', '0', 'value'], []]],
      ['value | trim | default()', [['value'], [['trim', []], ['default', []]]]],
      ['|default(0)', [[], [['default', ['0']]]]],
      ["value|default('a.b,c|d(e)')", [['value'], [['default', ['a.b,c|d(e)']]]]],
      ['value|replace("a,b", "x|y")', [['value'], [['replace', ['a,b', 'x|y']]]]],
      ["value|default('')", [['value'], [['default', ['']]]]],
      ["value|default('it\\'s')", [['value'], [['default', ["it's"]]]]],
      ["value|default('\\d+')", [['value'], [['default', ['\\d+']]]]],
      ['value|default(1.25)', [['value'], [['default', ['1.25']]]]],
    ];
  }

  /**
   * Tests rejection of malformed expressions.
   *
   * @dataProvider invalidExpressions
   */
  public function testInvalidExpressions(string $expression): void {
    $this->expectException(InvalidArgumentException::class);
    (new FilterExpressionParser())->parse($expression);
  }

  /**
   * Provides malformed filter expressions.
   */
  public static function invalidExpressions(): array {
    return array_map(static fn($expression) => [$expression], [
      'value|', 'value||trim', 'value|default(', 'value|default)',
      "value|default('open)", 'value|default(a,,b)',
      'value|default("a"suffix)', 'value|default(nested(value))',
      'value..property', '.value', 'value|default()suffix',
    ]);
  }

}
