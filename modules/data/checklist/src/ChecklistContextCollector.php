<?php

namespace Drupal\checklist;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Event\ChecklistCollectConfigContextsEvent;
use Drupal\checklist\Event\ChecklistCollectRuntimeContextsEvent;
use Drupal\checklist\Event\ChecklistEvents;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\typed_data_plus\Plugin\Context\DataContextDefinition;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Service to help collect contexts available for a particular checklist.
 */
class ChecklistContextCollector implements ChecklistContextCollectorInterface {

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Construct a checklist context collector.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $contextHandler
   *   Resolves branch context selectors.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher, protected ContextHandlerInterface $contextHandler) {
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function collectConfigContexts(ChecklistInterface $checklist, array $config_context = []) : array {
    $event = new ChecklistCollectConfigContextsEvent($checklist, $config_context);
    $this->eventDispatcher->dispatch($event, ChecklistEvents::COLLECT_CONFIG_CONTEXTS);
    return $event->getContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function collectRuntimeContexts(ChecklistInterface $checklist, ?ChecklistItemInterface $item = NULL): array {
    $event = new ChecklistCollectRuntimeContextsEvent($checklist);
    $this->eventDispatcher->dispatch($event, ChecklistEvents::COLLECT_RUNTIME_CONTEXTS);
    $contexts = $event->getContexts();
    $derivation = $item && $checklist->isItemActive($item) ? ($item->get('derivation')->first()?->getValue() ?? []) : [];
    foreach ($derivation['scopes'] ?? [] as $scope) {
      // Resolve inputs in the enclosing scope before adding local aliases.
      $mapping = $scope['context_mapping'] ?? [];
      $definitions = ChecklistContextMapping::definitions($scope['context_definitions'] ?? []);
      if ($mapping || $definitions) {
        foreach (array_keys($mapping) as $name) {
          if (isset($definitions[$name])) {
            continue;
          }
          if (!isset($contexts[$name])) {
            throw new \InvalidArgumentException(sprintf('Unknown branch input "%s".', $name));
          }
          $definitions[$name] = $contexts[$name]->getContextDefinition();
        }
        $container = ChecklistContextMapping::fromDefinitions($definitions, $mapping);
        $this->contextHandler->applyContextMapping($container, $contexts);
        $contexts = $container->getContexts() + $contexts;
      }
      $tree = $contexts['items'];
      $definition = clone $tree->getContextDefinition()->getDataDefinition();
      $values = $tree->getContextValue();
      $source_contexts = $contexts;
      foreach ($scope['aliases'] as $local => $qualified) {
        $property = $definition->getPropertyDefinition($qualified);
        if (!$property) {
          continue;
        }
        $definition->setPropertyDefinition($local, $property);
        $values[$local] = $values[$qualified] ?? NULL;
        // Keep direct item outcome selectors scoped as well as the items tree.
        foreach (array_keys($contexts) as $key) {
          if (str_starts_with($key, "item:{$local}:")) {
            unset($contexts[$key]);
          }
        }
        foreach ($source_contexts as $key => $context) {
          if (str_starts_with($key, "item:{$qualified}:")) {
            $contexts['item:' . $local . ':' . substr($key, strlen("item:{$qualified}:"))] = $context;
          }
        }
      }
      $contexts['items'] = new Context(DataContextDefinition::fromDataDefinition($definition), $values);
    }
    return $contexts;
  }

}
