<?php

declare(strict_types=1);

namespace Drupal\Tests\typed_data_plus\Kernel;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\KernelTests\KernelTestBase;

/**
 * Shared placeholders use filtered fetching without Entity Template.
 *
 * @group typed_data_plus
 */
class PlaceholderResolverTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'typed_data', 'typed_data_plus'];

  /**
   * Tests quoted filter arguments and escaping of untrusted values.
   */
  public function testQuotedArgumentsAndEscaping(): void {
    $resolver = $this->container->get('typed_data_plus.placeholder_resolver');
    $manager = $this->container->get('typed_data_manager');
    $data = ['text' => $manager->create(DataDefinition::create('string'), NULL)];
    $this->assertSame('a.b,c|d(e)', $resolver->replacePlaceHolders("{{text|default('a.b,c|d(e)')}}", $data));
    $data['text']->setValue('<b>unsafe</b>');
    $this->assertSame('&lt;b&gt;unsafe&lt;/b&gt;', $resolver->replacePlaceHolders('{{text}}', $data));
    $this->assertSame('UNSAFE', $resolver->replacePlaceHolders('{{text|striptags|upper}}', $data));
  }

  /**
   * Tests preserving or clearing unresolved placeholders.
   */
  public function testMissingAndMalformedValues(): void {
    $resolver = $this->container->get('typed_data_plus.placeholder_resolver');
    $data = ['text' => $this->container->get('typed_data_manager')->create(DataDefinition::create('string'), 'value')];
    foreach (['{{missing}}', "{{text|default('unclosed)}}"] as $text) {
      $this->assertSame($text, $resolver->replacePlaceHolders($text, $data));
      $this->assertSame('', $resolver->replacePlaceHolders($text, $data, NULL, ['clear' => TRUE]));
    }
  }

}
