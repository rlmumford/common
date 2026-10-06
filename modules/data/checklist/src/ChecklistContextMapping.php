<?php

namespace Drupal\checklist;

use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\ContextAwarePluginTrait;
use Drupal\Core\Plugin\PluginBase;

/**
 * Adapts declared branch inputs to the standard context handler and widget.
 *
 * This is a context container, not a separately discovered checklist handler.
 */
final class ChecklistContextMapping extends PluginBase implements ContextAwarePluginInterface {

  use ContextAwarePluginTrait;

  /**
   * Builds namespaced template input definitions from portable configuration.
   */
  public static function definitions(array $configuration): array {
    $definitions = [];
    foreach ($configuration as $name => $settings) {
      $definitions['template_context:' . $name] = ContextDefinition::create($settings['type'])
        ->setLabel($settings['label'])
        ->setDescription($settings['description'] ?? '')
        ->setRequired($settings['required'] ?? TRUE)
        ->setMultiple($settings['multiple'] ?? FALSE);
    }
    return $definitions;
  }

  /**
   * Creates a mapping container for known context definitions.
   */
  public static function fromDefinitions(array $definitions, array $mapping): self {
    return new self(['context_mapping' => $mapping], 'checklist_branch', ['context_definitions' => $definitions]);
  }

}
