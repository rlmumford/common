<?php

declare(strict_types=1);

namespace Drupal\typed_data_plus\Condition;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Render\AttachmentsInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\typed_data\Exception\InvalidArgumentException;
use Drupal\typed_data_plus\DataFetcherInterface;
use Drupal\typed_data_plus\FilterExpressionParser;

/**
 * Evaluates the data-predicate subset of CounselKit's condition language.
 *
 * No global user state or cached evaluations are retained by this service.
 */
final class ConditionEvaluator {

  public function __construct(protected ConditionParser $parser, protected DataFetcherInterface $fetcher) {}

  /**
   * Evaluates typed contexts, returning reasons and bubbleable metadata.
   *
   * Contexts map names to typed data; use typed NULL for unavailable values.
   * Unknown contexts/predicates and invalid syntax throw ConditionException.
   * All leaves are validated and evaluated, including unselected OR branches.
   */
  public function evaluate(string $expression, array $contexts): ConditionResult {
    $tree = $this->parser->parse($expression);
    $metadata = new BubbleableMetadata();
    $definitions = [];
    foreach ($contexts as $name => $data) {
      if (!$data instanceof TypedDataInterface) {
        throw new ConditionException("Context '$name' must contain typed data.");
      }
      $definitions[$name] = $data->getDataDefinition();
    }
    try {
      $this->validateNode($tree, $definitions);
      [$met, $reasons] = $this->evaluateNode($tree, $contexts, $metadata);
    }
    catch (InvalidArgumentException | PluginException $exception) {
      throw new ConditionException($exception->getMessage(), 0, $exception);
    }
    $result = new ConditionResult($met, $met ? [] : $reasons);
    $result->addCacheableDependency($metadata);
    $result->setAttachments($metadata->getAttachments());
    return $result;
  }

  /**
   * Validates syntax, predicates and selectors against expected definitions.
   *
   * Definitions are DataDefinitionInterface values; no data is fetched.
   */
  public function validate(string $expression, array $definitions): void {
    $tree = $this->parser->parse($expression);
    try {
      $this->validateNode($tree, $definitions);
    }
    catch (InvalidArgumentException | PluginException $exception) {
      throw new ConditionException($exception->getMessage(), 0, $exception);
    }
  }

  /**
   * Validates every branch without running filters.
   */
  private function validateNode(array $node, array $definitions): void {
    if (isset($node['children'])) {
      foreach ($node['children'] as $child) {
        $this->validateNode($child, $definitions);
      }
      return;
    }
    [$subject, , $argument] = $this->leaf($node['tokens']);
    if ($subject === 'NEVER') {
      return;
    }
    foreach ([$subject, $this->reference($argument)] as $selector) {
      if ($selector === NULL) {
        continue;
      }
      [$context] = $this->selector($selector);
      if (!isset($definitions[$context])) {
        throw new ConditionException("Unknown context '$context'.");
      }
      // Reuse definition-only fetching to validate paths and filter arguments.
      $suffix = substr($selector, strlen($context));
      try {
        $this->fetcher->fetchFilteredDefinition($definitions[$context], ltrim($suffix, '.'));
      }
      catch (InvalidArgumentException | PluginException $exception) {
        throw new ConditionException($exception->getMessage(), 0, $exception);
      }
    }
  }

  /**
   * Returns [met, unmet reasons] for a group or leaf.
   */
  private function evaluateNode(array $node, array $contexts, BubbleableMetadata $metadata): array {
    $reasons = [];
    if (isset($node['children'])) {
      $values = [];
      foreach ($node['children'] as $child) {
        [$value, $child_reasons] = $this->evaluateNode($child, $contexts, $metadata);
        $values[] = $value;
        $reasons = array_merge($reasons, $child_reasons);
      }
      $met = $node['operator'] === 'and' ? !in_array(FALSE, $values, TRUE) : in_array(TRUE, $values, TRUE);
    }
    else {
      [$subject, $predicate, $argument, $negated] = $this->leaf($node['tokens']);
      if ($subject === 'NEVER') {
        $met = FALSE;
      }
      else {
        $left = $this->value($subject, $contexts, $metadata);
        $reference = $this->reference($argument);
        $right = $reference !== NULL ? $this->value($reference, $contexts, $metadata) : $this->literal($argument);
        $met = $this->compare($predicate, $left, $right);
      }
      $met = $negated ? !$met : $met;
      if (!$met) {
        $reasons[] = implode(' ', $node['tokens']);
      }
    }
    if (!empty($node['negated'])) {
      $met = !$met;
      $reasons = $met ? [] : ['Negated condition was satisfied.'];
    }
    return [$met, $met ? [] : $reasons];
  }

  /**
   * Validates and decomposes a data-predicate leaf.
   */
  private function leaf(array $tokens): array {
    if ($tokens === ['NEVER']) {
      return ['NEVER', 'never', NULL, FALSE];
    }
    $subject = array_shift($tokens);
    $negated = strtolower($tokens[0] ?? '') === 'not';
    if ($negated) {
      array_shift($tokens);
    }
    $predicate = strtolower(array_shift($tokens) ?? '');
    $unary = in_array($predicate, ['exists', 'empty', 'notempty'], TRUE);
    $binary = in_array($predicate, ['==', '!=', '<>', '>', '>=', '<', '<=', 'contains', 'notcontains', 'in', 'notin'], TRUE);
    if ((!$unary && !$binary) || count($tokens) !== ($unary ? 0 : 1)) {
      throw new ConditionException("Unsupported or malformed predicate '$predicate'.");
    }
    $this->selector($subject);
    $argument = $tokens[0] ?? NULL;
    if ($argument !== NULL && $this->reference($argument) === NULL) {
      $this->literal($argument);
    }
    return [$subject, $predicate, $argument, $negated];
  }

  /**
   * Parses a selector and separates its context from its property/filter path.
   */
  private function selector(string $selector): array {
    [$path, $filters] = $this->fetcher->parsePropertyPathAndFilters($selector);
    $context = array_shift($path);
    if (!$context || !preg_match('/^[a-zA-Z0-9_-]+$/D', $context)) {
      throw new ConditionException('A selector must start with a context name.');
    }
    return [$context, $path, $filters];
  }

  /**
   * Fetches raw values through the shared typed-data/filter implementation.
   */
  private function value(string $selector, array $contexts, BubbleableMetadata $metadata): mixed {
    [$context, $path, $filters] = $this->selector($selector);
    if (!array_key_exists($context, $contexts)) {
      throw new ConditionException("Unknown context '$context'.");
    }
    $data = $contexts[$context];
    $this->collectMetadata($data, $metadata);
    try {
      $data = $this->fetcher->fetchDataBySubPaths($data, $path, $metadata);
      $this->collectMetadata($data, $metadata);
      return $this->fetcher->applyFiltersToValue($data, $filters, $metadata);
    }
    catch (MissingDataException) {
      return NULL;
    }
  }

  /**
   * Collects metadata even when a selector has no property traversal.
   */
  private function collectMetadata(TypedDataInterface $data, BubbleableMetadata $metadata): void {
    if ($data instanceof CacheableDependencyInterface) {
      $metadata->addCacheableDependency($data);
    }
    if ($data instanceof AttachmentsInterface) {
      $metadata->addAttachments($data->getAttachments());
    }
  }

  /**
   * Extracts an entire typed reference; never performs HTML placeholder output.
   */
  private function reference(?string $argument): ?string {
    if ($argument !== NULL && str_starts_with($argument, '{{')) {
      if (!str_ends_with($argument, '}}')) {
        throw new ConditionException('Invalid value reference.');
      }
      return trim(substr($argument, 2, -2));
    }
    return NULL;
  }

  /**
   * Parses a literal while preserving quoted strings and numeric/boolean types.
   */
  private function literal(?string $argument): mixed {
    if ($argument === NULL) {
      return NULL;
    }
    if ($argument[0] === "'" || $argument[0] === '"') {
      [, $filters] = (new FilterExpressionParser())->parse('|literal(' . $argument . ')');
      if (count($filters[0][1]) !== 1) {
        throw new ConditionException('Expected one quoted literal.');
      }
      return $filters[0][1][0];
    }
    if (!preg_match('/^[^\s(){}\'"|]+$/D', $argument)) {
      throw new ConditionException('Quote literal values containing punctuation.');
    }
    return match (strtolower($argument)) {
      'true' => TRUE,
      'false' => FALSE,
      'null' => NULL,
      default => is_numeric($argument) ? $argument + 0 : $argument,
    };
  }

  /**
   * Applies a data predicate; unavailable values never satisfy comparisons.
   */
  private function compare(string $predicate, mixed $left, mixed $right): bool {
    if ($predicate === 'exists') {
      return $left !== NULL;
    }
    if ($predicate === 'empty' || $predicate === 'notempty') {
      return $predicate === 'empty' ? empty($left) : !empty($left);
    }
    if ($left === NULL || $right === NULL) {
      return FALSE;
    }
    if (in_array($predicate, ['contains', 'notcontains', 'in', 'notin'], TRUE)) {
      [$haystack, $needle] = in_array($predicate, ['in', 'notin'], TRUE) ? [$right, $left] : [$left, $right];
      if (is_array($haystack)) {
        $met = in_array($needle, $haystack, TRUE);
      }
      elseif (is_string($haystack) && is_scalar($needle)) {
        $met = str_contains($haystack, (string) $needle);
      }
      else {
        throw new ConditionException('Membership requires a list or string.');
      }
      return in_array($predicate, ['notcontains', 'notin'], TRUE) ? !$met : $met;
    }
    if (!is_scalar($left) || !is_scalar($right)) {
      throw new ConditionException('Comparison requires scalar values.');
    }
    return match ($predicate) {
      '==' => $left == $right,
      '!=', '<>' => $left != $right,
      '>' => $left > $right,
      '>=' => $left >= $right,
      '<' => $left < $right,
      '<=' => $left <= $right,
    };
  }

}
