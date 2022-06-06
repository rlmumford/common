<?php

namespace Drupal\checklist\Plugin\ChecklistItemHandler;

use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Base class for checklist item handlers that use contexts.
 */
abstract class ContextAwareChecklistItemHandlerBase extends ChecklistItemHandlerBase implements ContextAwarePluginInterface {
  use ContextAwarePluginTrait;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary(): array {
    $build = [];

    $mapping = $this->getContextMapping();
    foreach ($this->getContextDefinitions() as $name => $definition) {
      $build['context'][$name] = [
        '#type' => 'item',
        '#title' => $definition->getLabel(),
        '#markup' => $mapping[$name] ?? new TranslatableMarkup('Undefined'),
      ];
    }

    if (!empty($build['context'])) {
      $build['context'] += [
        '#weight' => 99,
        '#type' => 'details',
        '#title' => new TranslatableMarkup('Context'),
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function isActionable(): bool {
    // It's not actionable if any required context does not have a value.
    foreach ($this->getContextDefinitions() as $name => $definition) {
      if ($definition->isRequired() && !$this->getContext($name)->hasContextValue()) {
        return FALSE;
      }
    }

    return parent::isActionable();
  }

}
