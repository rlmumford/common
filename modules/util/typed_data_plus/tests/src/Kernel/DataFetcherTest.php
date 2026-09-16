<?php

declare(strict_types=1);

namespace Drupal\Tests\typed_data_plus\Kernel;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Markup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\typed_data\DataFilterInterface;
use Drupal\typed_data\DataFilterManagerInterface;
use Drupal\typed_data\Exception\InvalidArgumentException;
use Drupal\typed_data_plus\DataFetcher;
use Drupal\typed_data_plus\DataFetcherInterface;
use Drupal\typed_data_plus\WrappedValueFilterInterface;

/**
 * Exercises filtered fetching with Drupal's real typed data manager.
 *
 * @group typed_data_plus
 */
class DataFetcherTest extends KernelTestBase {

  protected static $modules = ['system', 'typed_data', 'typed_data_plus'];

  public function testStandaloneServiceAndListTraversal(): void {
    $fetcher = $this->container->get('typed_data_plus.data_fetcher');
    $this->assertInstanceOf(DataFetcherInterface::class, $fetcher);
    $this->assertNotSame($fetcher, $this->container->get('typed_data.data_fetcher'));
    $definition = ListDataDefinition::create('string');
    $data = $this->container->get('typed_data_manager')->create($definition, ['first', 'second']);
    $this->assertSame('second', $fetcher->fetchFilteredData($data, '1')->getValue());
    $this->assertSame('string', $fetcher->fetchFilteredDefinition($definition, '1')->getDataType());
    $this->assertSame($data, $fetcher->applyFilters($data, []));
    $this->expectException(MissingDataException::class);
    $fetcher->fetchFilteredData($data, '9');
  }

  public function testScalarTransformRefreshesWrappedValue(): void {
    $upper = $this->filter(static fn($definition, $value) => strtoupper($value));
    $wrapped = $this->filter(static function ($definition, TypedDataInterface $value) {
      return $value->getValue() . '!';
    }, TRUE);
    $fetcher = $this->fetcher(['upper' => $upper, 'wrapped' => $wrapped]);
    $data = $this->stringData('before');
    $result = $fetcher->fetchFilteredData($data, '|upper|wrapped');
    $this->assertSame('BEFORE!', $result->getValue());
    $this->assertSame('before', $data->getValue());
  }

  public function testTypedResultPreservesIdentityAndDefinition(): void {
    $result = $this->container->get('typed_data_manager')->create(DataDefinition::create('integer'), 42);
    $filter = $this->filter(static fn() => $result);
    $fetcher = $this->fetcher(['typed' => $filter]);
    $this->assertSame($result, $fetcher->applyFilters($this->stringData('input'), [['typed', []]]));
    $this->assertSame(42, $fetcher->applyFiltersToValue($this->stringData('input'), [['typed', []]]));
  }

  public function testMarkupAndCacheMetadata(): void {
    $markup = Markup::create('<b>value</b>');
    $render = $this->filter(static function ($definition, $value, $arguments, BubbleableMetadata $metadata) use ($markup) {
      $metadata->addCacheTags(['example:1']);
      $metadata->addCacheContexts(['user.permissions']);
      $metadata->addAttachments(['library' => ['example/view']]);
      return $markup;
    });
    $strip = $this->filter(static function ($definition, $value) {
      // The scalar argument type would reject Markup with strict types enabled.
      return strip_tags($value);
    });
    $fetcher = $this->fetcher(['render' => $render, 'strip' => $strip]);
    $metadata = new BubbleableMetadata();
    $data = $this->stringData('input');
    $this->assertSame($markup, $fetcher->applyFiltersToValue($data, [['render', []]], $metadata));
    $this->assertSame('value', $fetcher->applyFiltersToValue($data, [['render', []], ['strip', []]], $metadata));
    $this->assertContains('example:1', $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertContains('example/view', $metadata->getAttachments()['library']);
  }

  public function testNullAwareFilter(): void {
    $fallback = $this->filter(static fn($definition, $value, $arguments) => $value ?? $arguments[0], FALSE, TRUE);
    $fetcher = $this->fetcher(['fallback' => $fallback]);
    $this->assertSame('a.b,c|d', $fetcher->fetchFilteredData($this->stringData(NULL), "|fallback('a.b,c|d')")->getValue());
  }

  public function testMissingValueDoesNotInvokeFilter(): void {
    $filter = $this->createMock(DataFilterInterface::class);
    $filter->method('canFilter')->willReturn(TRUE);
    $filter->expects($this->never())->method('filter');
    $fetcher = $this->fetcher(['strict' => $filter]);
    $this->expectException(MissingDataException::class);
    $fetcher->fetchFilteredData($this->stringData(NULL), '|strict');
  }

  public function testValidationDoesNotInvokeFilter(): void {
    $filter = $this->createMock(DataFilterInterface::class);
    $filter->method('canFilter')->willReturn(TRUE);
    $filter->method('validateArguments')->willReturn(['Argument required.']);
    $filter->expects($this->never())->method('filter');
    $fetcher = $this->fetcher(['invalid' => $filter]);
    $this->expectException(InvalidArgumentException::class);
    $fetcher->fetchFilteredData($this->stringData('input'), '|invalid');
  }

  public function testDefinitionOnlyFiltering(): void {
    $filter = $this->createMock(DataFilterInterface::class);
    $filter->method('canFilter')->willReturn(TRUE);
    $filter->method('filtersTo')->willReturn(DataDefinition::create('integer'));
    $filter->expects($this->never())->method('filter');
    $fetcher = $this->fetcher(['length' => $filter]);
    $this->assertSame('integer', $fetcher->fetchFilteredDefinition(DataDefinition::create('string'), '|length')->getDataType());
  }

  protected function stringData(?string $value): TypedDataInterface {
    return $this->container->get('typed_data_manager')->create(DataDefinition::create('string'), $value);
  }

  protected function fetcher(array $filters): DataFetcher {
    $manager = $this->createMock(DataFilterManagerInterface::class);
    $manager->method('createInstance')->willReturnCallback(static fn($id) => $filters[$id]);
    return new DataFetcher($manager, $this->container->get('typed_data_manager'));
  }

  protected function filter(callable $callback, bool $wrapped = FALSE, bool $allows_null = FALSE): DataFilterInterface {
    $filter = $this->createMock(WrappedValueFilterInterface::class);
    $filter->method('canFilter')->willReturn(TRUE);
    $filter->method('usesWrappedValue')->willReturn($wrapped);
    $filter->method('allowsNullValues')->willReturn($allows_null);
    $filter->method('filtersTo')->willReturnCallback(static fn(DataDefinitionInterface $definition) => $definition);
    $filter->method('filter')->willReturnCallback($callback);
    return $filter;
  }

}
