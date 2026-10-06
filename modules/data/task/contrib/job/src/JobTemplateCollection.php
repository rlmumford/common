<?php

namespace Drupal\task_job;

/**
 * Expands frozen collection membership against live job template definitions.
 */
final class JobTemplateCollection {

  /**
   * Inserts each recorded invocation directly after its calling item.
   */
  public static function expand(array $definitions, array $templates, array $invocations): array {
    $result = [];
    foreach ($definitions as $name => $definition) {
      if (isset($result[$name])) {
        throw new \InvalidArgumentException('Collection expansion collides with an existing item.');
      }
      $result[$name] = $definition;
      if (!isset($invocations[$name])) {
        continue;
      }
      $configuration = $invocations[$name]['configuration'];
      $template = $configuration['template'];
      if (!isset($templates[$template])) {
        throw new \InvalidArgumentException('A recorded collection template is missing.');
      }
      for ($index = 0; $index < $invocations[$name]['count']; $index++) {
        $mapping = $configuration['context_mapping'];
        $mapping[$configuration['collection_input']] = "item:{$name}:members.{$index}";
        // The persisted membership position is immutable, unlike a live delta.
        $prefix = $name . '__member_' . $index . '__' . $template . '__';
        $children = JobChecklistExpansion::instance($template, $templates, $prefix, $mapping);
        foreach ($children as &$child) {
          $derivation = $child['derivation'];
          $child['derivation']['requirements'] = ($definition['derivation']['requirements'] ?? []) + [
            $name => ['outcome' => 'template', 'value' => $template],
          ] + ($derivation['requirements'] ?? []);
          $child['derivation']['scopes'] = [
            ...($definition['derivation']['scopes'] ?? []),
            ...$derivation['scopes'],
          ];
        }
        unset($child);
        $children = self::expand($children, $templates, $invocations);
        if (array_intersect_key($result, $children) || array_intersect_key($definitions, $children)) {
          throw new \InvalidArgumentException('Collection expansion collides with an existing item.');
        }
        $result += $children;
        if (count($result) > 1000) {
          throw new \InvalidArgumentException('The task checklist exceeds 1,000 definitions.');
        }
      }
    }
    if (count($result) > 1000) {
      throw new \InvalidArgumentException('The task checklist exceeds 1,000 definitions.');
    }
    return $result;
  }

}
