<?php

declare(strict_types=1);

namespace Drupal\typed_data_plus\Condition;

/**
 * Parses complete condition strings without silently discarding trailing input.
 */
final class ConditionParser {

  /**
   * Parses boolean groups into a tree of operator/children or leaf tokens.
   *
   * A group must use one boolean operator; mixed AND/OR requires parentheses.
   * Quotes, placeholders and filter arguments protect their internal spaces.
   */
  public function parse(string $expression, int $depth = 0): array {
    if (strlen($expression) > 65536 || $depth > 64) {
      throw new ConditionException('Condition exceeds the parsing limit.');
    }
    $tokens = $this->tokens(trim($expression));
    if (!$tokens) {
      if ($depth > 0) {
        throw new ConditionException('Empty condition group.');
      }
      return ['operator' => 'and', 'children' => []];
    }
    $groups = [];
    $current = [];
    $operator = NULL;
    foreach ($tokens as $token) {
      $lower = strtolower($token);
      if (in_array($lower, ['and', 'or'], TRUE)) {
        if (!$current || ($operator !== NULL && $operator !== $lower)) {
          throw new ConditionException('Use parentheses for mixed AND/OR conditions; operands cannot be empty.');
        }
        $operator = $lower;
        $groups[] = $current;
        $current = [];
      }
      else {
        $current[] = $token;
      }
    }
    if (!$current) {
      throw new ConditionException('Missing operand after boolean operator.');
    }
    $groups[] = $current;
    $children = [];
    foreach ($groups as $group) {
      $negated = strtolower($group[0]) === 'not';
      if ($negated) {
        array_shift($group);
      }
      if (!$group) {
        throw new ConditionException('Missing operand after NOT.');
      }
      if ($group[0][0] === '(') {
        if (count($group) !== 1 || !str_ends_with($group[0], ')')) {
          throw new ConditionException('Unexpected text beside condition group.');
        }
        $child = $this->parse(substr($group[0], 1, -1), $depth + 1);
      }
      else {
        $child = ['tokens' => $group];
      }
      $child['negated'] = $negated;
      $children[] = $child;
    }
    return ['operator' => $operator ?? 'and', 'children' => $children];
  }

  /**
   * Splits on unprotected whitespace and rejects unbalanced delimiters.
   */
  private function tokens(string $expression): array {
    $tokens = [];
    $start = 0;
    $depth = 0;
    $reference = FALSE;
    $quote = NULL;
    $length = strlen($expression);
    for ($i = 0; $i < $length; $i++) {
      $character = $expression[$i];
      if ($quote !== NULL) {
        if ($character === '\\') {
          $i++;
        }
        elseif ($character === $quote) {
          $quote = NULL;
        }
        continue;
      }
      if ($character === "'" || $character === '"') {
        $quote = $character;
      }
      elseif (substr($expression, $i, 2) === '{{') {
        if ($reference) {
          throw new ConditionException('Nested value reference.');
        }
        $reference = TRUE;
        $i++;
      }
      elseif (substr($expression, $i, 2) === '}}') {
        if (!$reference) {
          throw new ConditionException('Unexpected closing value reference.');
        }
        $reference = FALSE;
        $i++;
      }
      elseif ($character === '(') {
        $depth++;
      }
      elseif ($character === ')') {
        if (--$depth < 0) {
          throw new ConditionException('Unexpected closing parenthesis.');
        }
      }
      elseif (ctype_space($character) && !$reference && $depth === 0) {
        if ($i > $start) {
          $tokens[] = substr($expression, $start, $i - $start);
        }
        $start = $i + 1;
      }
    }
    if ($quote !== NULL || $reference || $depth !== 0) {
      throw new ConditionException('Unclosed quote, reference or parenthesis.');
    }
    if ($start < $length) {
      $tokens[] = substr($expression, $start);
    }
    return $tokens;
  }

}
