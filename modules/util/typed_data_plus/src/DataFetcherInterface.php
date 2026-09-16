<?php

declare(strict_types=1);

namespace Drupal\typed_data_plus;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\typed_data\DataFetcherInterface as BaseDataFetcherInterface;

/**
 * Fetches typed data and applies inline filter expressions.
 */
interface DataFetcherInterface extends BaseDataFetcherInterface {

  /**
   * Parses a path into [property segments, [[filter ID, arguments], ...]].
   *
   * Arguments are strings; single/double quotes protect commas, pipes and dots.
   * Empty parentheses mean no arguments; quotes can represent an empty string.
   *
   * @throws \Drupal\typed_data\Exception\InvalidArgumentException
   *   When the expression is malformed.
   */
  public function parsePropertyPathAndFilters(string $expression): array;

  /**
   * Fetches a property and filters it, preserving its resulting definition.
   */
  public function fetchFilteredData(TypedDataInterface $data, string $expression, ?BubbleableMetadata $metadata = NULL, ?string $langcode = NULL): TypedDataInterface;

  /**
   * Resolves a filtered definition without executing filters or fetching data.
   */
  public function fetchFilteredDefinition(DataDefinitionInterface $definition, string $expression, ?string $langcode = NULL): DataDefinitionInterface;

  /**
   * Applies parsed filters, returning typed data (including its definition).
   */
  public function applyFilters(TypedDataInterface $data, array $filters, ?BubbleableMetadata $metadata = NULL): TypedDataInterface;

  /**
   * Applies parsed filters, preserving trusted Markup in the final raw value.
   */
  public function applyFiltersToValue(TypedDataInterface $data, array $filters, ?BubbleableMetadata $metadata = NULL): mixed;

}
