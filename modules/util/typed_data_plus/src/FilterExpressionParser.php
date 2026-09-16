<?php

declare(strict_types=1);

namespace Drupal\typed_data_plus;

use Drupal\typed_data\Exception\InvalidArgumentException;

/**
 * Parses property paths and literal filter arguments without evaluating code.
 */
final class FilterExpressionParser {

  /**
   * Returns property segments and filter ID/argument pairs.
   */
  public function parse(string $expression): array {
    $parts = $this->split(trim($expression), '|');
    $path = trim(array_shift($parts));
    $properties = $path === '' ? [] : explode('.', $path);
    foreach ($properties as $property) {
      if ($property === '' || preg_match('/[\s()\'"|]/', $property)) {
        throw new InvalidArgumentException('Invalid property path.');
      }
    }
    $filters = [];
    foreach ($parts as $part) {
      if (!preg_match('/^\s*([a-z_][a-z0-9_:]*)\s*(?:\((.*)\))?\s*$/is', $part, $match)) {
        throw new InvalidArgumentException('Invalid filter expression.');
      }
      $arguments = [];
      if (isset($match[2]) && trim($match[2]) !== '') {
        foreach ($this->split($match[2], ',') as $argument) {
          $argument = trim($argument);
          if ($argument === '') {
            throw new InvalidArgumentException('Empty argument must be quoted.');
          }
          if ($argument[0] === "'" || $argument[0] === '"') {
            $quote = $argument[0];
            // The scanner validates balance; reject text outside the quotes.
            $pattern = '/^' . $quote . '((?:[^' . $quote . '\\\\]|\\\\.)*)' . $quote . '$/s';
            if (!preg_match($pattern, $argument, $quoted)) {
              throw new InvalidArgumentException('Invalid quoted argument.');
            }
            $argument = str_replace(['\\' . $quote, '\\\\'], [$quote, '\\'], $quoted[1]);
          }
          elseif (preg_match('/[()\'\"]/', $argument)) {
            throw new InvalidArgumentException('Quote arguments containing parentheses or quotes.');
          }
          $arguments[] = $argument;
        }
      }
      $filters[] = [$match[1], $arguments];
    }
    return [$properties, $filters];
  }

  /**
   * Splits outside quotes and parentheses, validating balanced delimiters.
   */
  private function split(string $expression, string $delimiter): array {
    $parts = [];
    $start = 0;
    $depth = 0;
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
      elseif ($character === '(') {
        $depth++;
      }
      elseif ($character === ')') {
        if (--$depth < 0) {
          throw new InvalidArgumentException('Unmatched closing parenthesis.');
        }
      }
      elseif ($character === $delimiter && $depth === 0) {
        $parts[] = substr($expression, $start, $i - $start);
        $start = $i + 1;
      }
    }
    if ($quote !== NULL || $depth !== 0) {
      throw new InvalidArgumentException('Unclosed quote or parenthesis.');
    }
    $parts[] = substr($expression, $start);
    return $parts;
  }

}
