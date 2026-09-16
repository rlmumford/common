<?php

declare(strict_types=1);

namespace Drupal\typed_data_plus;

use Drupal\Component\Render\HtmlEscapedText;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\typed_data\Exception\InvalidArgumentException;
use Drupal\typed_data\PlaceholderResolver as TypedDataPlaceholderResolver;

/**
 * Resolves placeholders through the shared filtered-data API.
 *
 * Adapted from Entity Template alpha17 and Typed Data 2.1.1. Upstream provides
 * scanning/replacement but has no extension point for its inline filter loop.
 */
class PlaceholderResolver extends TypedDataPlaceholderResolver {

  public function __construct(DataFetcherInterface $data_fetcher, \Drupal\typed_data\DataFilterManagerInterface $data_filter_manager) {
    parent::__construct($data_fetcher, $data_filter_manager);
  }

  /**
   * {@inheritdoc}
   */
  protected function parseMainPlaceholderPart(string $main_part, string $placeholder): array {
    return $this->dataFetcher->parsePropertyPathAndFilters($main_part);
  }


  /**
   * {@inheritdoc}
   */
  public function resolvePlaceholders(string $text, array $data = [], ?BubbleableMetadata $bubbleable_metadata = NULL, array $options = []): array {
    $options += [
      'langcode' => NULL,
      'clear' => FALSE,
    ];
    $placeholder_by_data = $this->scan($text);
    if (empty($placeholder_by_data)) {
      return [];
    }

    $replacements = [];
    foreach ($placeholder_by_data as $data_name => $placeholders) {
      foreach ($placeholders as $placeholder_main_part => $placeholder) {
        try {
          if (!isset($data[$data_name])) {
            throw new MissingDataException("There is no data with the name '$data_name' available.");
          }
          [$property_sub_paths, $filters] = $this->parseMainPlaceholderPart($placeholder_main_part, $placeholder);
          $fetched_data = $this->dataFetcher->fetchDataBySubPaths($data[$data_name], $property_sub_paths, $bubbleable_metadata, $options['langcode']);

          // Share filter semantics with selectors and future condition consumers.
          if ($filters) {
            $value = $this->dataFetcher->applyFiltersToValue($fetched_data, $filters, $bubbleable_metadata);
          }
          else {
            $value = $fetched_data->getString();
          }

          // Escape the tokens, unless they are explicitly markup.
          $replacements[$placeholder] = $value instanceof MarkupInterface ? $value : new HtmlEscapedText($value);
        }
        catch (InvalidArgumentException $e) {
          // Should we log warnings if there are problems other than missing
          // data, like syntactically invalid placeholders?
          if (!empty($options['clear'])) {
            $replacements[$placeholder] = '';
          }
        }
        catch (MissingDataException $e) {
          if (!empty($options['clear'])) {
            $replacements[$placeholder] = '';
          }
        }
      }
    }
    return $replacements;
  }

}
