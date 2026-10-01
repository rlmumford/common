<?php

namespace Drupal\checklist;

use Drupal\Core\Plugin\ContextAwarePluginInterface;
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
   * Creates a mapping container for known context definitions.
   */
  public static function fromDefinitions(array $definitions, array $mapping): self {
    return new self(['context_mapping' => $mapping], 'checklist_branch', ['context_definitions' => $definitions]);
  }

}
