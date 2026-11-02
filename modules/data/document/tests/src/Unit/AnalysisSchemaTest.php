<?php

namespace Drupal\Tests\document\Unit;

use Drupal\document\Review\AnalysisSchema;
use Drupal\Tests\UnitTestCase;

/**
 * Checks the Draft 7 contract on Drupal core's supported library versions.
 *
 * @group document
 */
class AnalysisSchemaTest extends UnitTestCase {

  /**
   * Tests required values, conditional schemas and lossless JSON output.
   */
  public function testDraftSevenContract(): void {
    $schema = json_encode([
      'type' => 'object',
      'properties' => [
        'approved' => ['type' => 'boolean'],
        'reason' => ['type' => 'string', 'minLength' => 1],
      ],
      'required' => ['approved'],
      'additionalProperties' => FALSE,
      'if' => ['properties' => ['approved' => ['const' => FALSE]]],
      'then' => ['required' => ['reason']],
    ]);
    $analysis = ['approved' => FALSE, 'reason' => 'Needs review'];
    $this->assertSame($analysis, json_decode(AnalysisSchema::validate($schema, $analysis), TRUE));
    $this->assertSame(['approved' => TRUE], json_decode(AnalysisSchema::validate($schema, ['approved' => TRUE]), TRUE));
    $this->expectException(\InvalidArgumentException::class);
    AnalysisSchema::validate($schema, ['approved' => FALSE]);
  }

  /**
   * Tests schema definitions fail closed before validation or URI retrieval.
   *
   * @dataProvider invalidSchemaProvider
   */
  public function testInvalidSchema(string $schema): void {
    $this->expectException(\InvalidArgumentException::class);
    AnalysisSchema::parse($schema);
  }

  /**
   * Provides invalid definitions and forbidden external references.
   */
  public static function invalidSchemaProvider(): array {
    return [
      ['{'],
      ['{"type":"array"}'],
      ['{"type":"object","properties":{"value":{"type":"unknown"}}}'],
      ['{"type":"object","$schema":"https://json-schema.org/draft/2020-12/schema"}'],
      ['{"type":"object","$id":"https://example.test/schema"}'],
      ['{"type":"object","properties":{"value":{"$ref":"https://example.test/schema"}}}'],
    ];
  }

}
