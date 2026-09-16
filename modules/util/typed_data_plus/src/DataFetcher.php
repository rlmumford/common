<?php

declare(strict_types=1);

namespace Drupal\typed_data_plus;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\typed_data\DataFetcher as BaseDataFetcher;
use Drupal\typed_data\DataFilterInterface;
use Drupal\typed_data\DataFilterManagerInterface;
use Drupal\typed_data\Exception\InvalidArgumentException;

/**
 * Shared filtered fetching, adapted from Entity Template's extended fetcher.
 *
 * Uses a separate service ID during the coordinated Entity Template migration.
 */
class DataFetcher extends BaseDataFetcher implements DataFetcherInterface {

  public function __construct(
    protected DataFilterManagerInterface $dataFilterManager,
    protected TypedDataManagerInterface $typedDataManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function parsePropertyPathAndFilters(string $expression): array {
    return (new FilterExpressionParser())->parse($expression);
  }

  /**
   * {@inheritdoc}
   */
  public function fetchFilteredData(TypedDataInterface $data, string $expression, ?BubbleableMetadata $metadata = NULL, ?string $langcode = NULL): TypedDataInterface {
    [$path, $filters] = $this->parsePropertyPathAndFilters($expression);
    return $this->applyFilters($this->fetchDataBySubPaths($data, $path, $metadata, $langcode), $filters, $metadata);
  }

  /**
   * {@inheritdoc}
   */
  public function fetchFilteredDefinition(DataDefinitionInterface $definition, string $expression, ?string $langcode = NULL): DataDefinitionInterface {
    [$path, $filters] = $this->parsePropertyPathAndFilters($expression);
    $definition = $this->fetchDefinitionBySubPaths($definition, $path, $langcode);
    foreach ($filters as [$id, $arguments]) {
      $definition = $this->validatedFilter($id, $definition, $arguments)->filtersTo($definition, $arguments);
    }
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function applyFilters(TypedDataInterface $data, array $filters, ?BubbleableMetadata $metadata = NULL): TypedDataInterface {
    [$value, $definition, $wrapped] = $this->runFilters($data, $filters, $metadata);
    return $wrapped ?? $this->typedDataManager->create($definition, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function applyFiltersToValue(TypedDataInterface $data, array $filters, ?BubbleableMetadata $metadata = NULL): mixed {
    return $this->runFilters($data, $filters, $metadata)[0];
  }

  /**
   * Runs filters while keeping the current raw and wrapped values consistent.
   */
  protected function runFilters(TypedDataInterface $data, array $filters, ?BubbleableMetadata $metadata): array {
    $value = $data->getValue();
    $definition = $data->getDataDefinition();
    $wrapped = $data;
    foreach ($filters as [$id, $arguments]) {
      $filter = $this->validatedFilter($id, $definition, $arguments);
      if ($value === NULL && !$filter->allowsNullValues()) {
        throw new MissingDataException("There is no data value for filter '$id' to work on.");
      }
      // Support existing Entity Template filters until they adopt the interface.
      $uses_wrapped = ($filter instanceof WrappedValueFilterInterface || is_callable([$filter, 'usesWrappedValue'])) && $filter->usesWrappedValue();
      $input = $uses_wrapped
        ? ($wrapped ?? $this->typedDataManager->create($definition, $value))
        : ($value instanceof MarkupInterface ? (string) $value : $value);
      $value = $filter->filter($definition, $input, $arguments, $metadata);
      $definition = $filter->filtersTo($definition, $arguments);
      // A scalar transform invalidates the previous wrapper. Rebuild lazily so
      // trusted Markup is preserved for raw-value consumers at the end of a chain.
      $wrapped = $value instanceof TypedDataInterface ? $value : NULL;
      if ($wrapped !== NULL) {
        $definition = $wrapped->getDataDefinition();
        $value = $wrapped->getValue();
      }
    }
    return [$value, $definition, $wrapped];
  }

  /**
   * Validates filters consistently for definition-only and runtime consumers.
   */
  protected function validatedFilter(string $id, DataDefinitionInterface $definition, array $arguments): DataFilterInterface {
    $filter = $this->dataFilterManager->createInstance($id);
    if (!$filter->canFilter($definition)) {
      throw new InvalidArgumentException("Filter '$id' cannot process this data type.");
    }
    $errors = $filter->validateArguments($definition, $arguments);
    if ($errors) {
      throw new InvalidArgumentException(implode(' ', array_map('strval', $errors)));
    }
    return $filter;
  }

}
