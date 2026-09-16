<?php

namespace Drupal\typed_data_plus\Plugin\Context;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\Core\Plugin\Context\ContextHandler as CoreContextHandler;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\TypedData\ListDataDefinitionInterface;
use Drupal\typed_data_plus\DataFetcherInterface;

/**
 * Resolves local and global context selectors using filtered typed data.
 */
class ContextHandler extends CoreContextHandler {

  public function __construct(protected DataFetcherInterface $dataFetcher, protected ContextRepositoryInterface $contextRepository) {}

  /**
   * Returns available contexts, with caller definitions taking priority.
   */
  public function getAvailableContexts(array $contexts = []): array {
    return $contexts + $this->contextRepository->getAvailableContexts();
  }

  /**
   * {@inheritdoc}
   *
   * Core's standard select widget needs concrete selector IDs, not root IDs
   * whose descendants happen to match. Enumerate definitions, never live data.
   * Bound discovery for recursive entity references; deeper paths remain valid.
   */
  public function getMatchingContexts(array $contexts, ContextDefinitionInterface $definition) {
    $matches = [];
    foreach ($this->getAvailableContexts($contexts) as $name => $context) {
      $budget = 128;
      foreach ($this->selectors($context->getContextDefinition()->getDataDefinition(), $name, 0, $budget) as $selector => $data_definition) {
        $candidate = $selector === $name ? $context : new Context(DataContextDefinition::fromDataDefinition($data_definition)->setLabel($selector));
        if ($definition->isMultiple() === $candidate->getContextDefinition()->isMultiple() && $definition->isSatisfiedBy($candidate)) {
          $matches[$selector] = $candidate;
        }
      }
    }
    return $matches;
  }

  /**
   * Enumerates a bounded set of selector definitions for standard select lists.
   */
  protected function selectors(DataDefinitionInterface $definition, string $path, int $depth, int &$budget): \Generator {
    if ($budget-- <= 0) {
      return;
    }
    yield $path => $definition;
    if ($depth >= 3) {
      return;
    }
    if ($definition instanceof DataReferenceDefinitionInterface) {
      $definition = $definition->getTargetDefinition();
    }
    if ($definition instanceof ListDataDefinitionInterface) {
      yield from $this->selectors($definition->getItemDefinition(), $path . '.0', $depth + 1, $budget);
    }
    elseif ($definition instanceof ComplexDataDefinitionInterface) {
      foreach ($definition->getPropertyDefinitions() as $name => $property) {
        if ($budget <= 0) {
          break;
        }
        yield from $this->selectors($property, $path . '.' . $name, $depth + 1, $budget);
      }
    }
  }

  /**
   * Finds the longest available root, including namespaced global context IDs.
   */
  protected function splitSelector(string $selector, array $contexts): ?array {
    $names = array_keys($contexts);
    usort($names, static fn(string $a, string $b) => strlen($b) <=> strlen($a));
    foreach ($names as $name) {
      if ($selector === $name || str_starts_with($selector, $name . '.') || str_starts_with($selector, $name . '|')) {
        return [$name, ltrim(substr($selector, strlen($name)), '.')];
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function applyContextMapping(ContextAwarePluginInterface $plugin, $contexts, $mappings = []) {
    $mappings += $plugin->getContextMapping();
    $available = NULL;
    foreach ($plugin->getContextDefinitions() as $slot => $definition) {
      $selector = $mappings[$slot] ?? $slot;
      $parts = $this->splitSelector($selector, $contexts);
      if ($parts === NULL && str_starts_with($selector, '@')) {
        // Only providers advertised by the repository may be loaded.
        $available ??= $this->contextRepository->getAvailableContexts();
        $parts = $this->splitSelector($selector, $available);
        if ($parts !== NULL) {
          $contexts += $this->contextRepository->getRuntimeContexts([$parts[0]]);
        }
      }
      if ($parts === NULL || !isset($contexts[$parts[0]])) {
        continue;
      }
      [$root, $expression] = $parts;
      $source = $contexts[$root];
      // Even direct assignments pass through the same fetcher and type checks.
      $result_definition = $this->dataFetcher->fetchFilteredDefinition($source->getContextDefinition()->getDataDefinition(), $expression);
      $candidate_definition = DataContextDefinition::fromDataDefinition($result_definition);
      $metadata = BubbleableMetadata::createFromObject($source);
      try {
        $data = $this->dataFetcher->fetchFilteredData($source->getContextData(), $expression, $metadata);
        $candidate = new Context($candidate_definition, $data);
      }
      catch (MissingDataException) {
        $candidate = new Context($candidate_definition);
      }
      $candidate->addCacheableDependency($metadata);
      if ($definition->isMultiple() !== $candidate_definition->isMultiple() || !$definition->isSatisfiedBy($candidate)) {
        throw new ContextException("Selector '$selector' does not satisfy context '$slot'.");
      }
      $contexts[$selector] = $candidate;
    }
    // Preserve core's required/optional checks and unmapped-context errors.
    parent::applyContextMapping($plugin, $contexts, $mappings);
  }

}
