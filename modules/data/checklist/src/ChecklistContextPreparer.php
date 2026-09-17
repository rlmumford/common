<?php

namespace Drupal\checklist;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Component\Plugin\Exception\MissingValueContextException;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;

/**
 * Assigns fresh runtime contexts before evaluating or executing an item.
 */
class ChecklistContextPreparer {

  /**
   * Constructs the context preparer.
   *
   * @param \Drupal\checklist\ChecklistContextCollectorInterface $collector
   *   The checklist context collector.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $contextHandler
   *   The selector-aware context handler.
   */
  public function __construct(
    protected ChecklistContextCollectorInterface $collector,
    protected ContextHandlerInterface $contextHandler,
  ) {}

  /**
   * Replaces a handler's contexts using current outcomes and providers.
   *
   * Missing required values block processing and completion checks. Other
   * mapping errors propagate as configuration errors. This does not check
   * access or switch accounts; callers own execution identity.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The current checklist, including unsaved outcomes from earlier items.
   * @param \Drupal\checklist\Entity\ChecklistItemInterface $item
   *   The item whose handler will receive contexts.
   *
   * @return bool
   *   TRUE if every required context has a value.
   */
  public function prepare(ChecklistInterface $checklist, ChecklistItemInterface $item): bool {
    $handler = $item->getHandler();
    if (!$handler instanceof ContextAwarePluginInterface) {
      return TRUE;
    }

    // Core mapping does not clear previous values for now-unavailable sources.
    foreach ($handler->getContextDefinitions() as $name => $definition) {
      $handler->setContext($name, new Context($definition));
    }
    try {
      $this->contextHandler->applyContextMapping($handler, $this->collector->collectRuntimeContexts($checklist));
    }
    catch (MissingValueContextException) {
      return FALSE;
    }
    return TRUE;
  }

}
