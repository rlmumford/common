<?php

namespace Drupal\typed_data_context_assignment\Plugin\Context;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Plugin\Exception\MissingValueContextException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\Core\Plugin\Context\ContextHandler as CoreContextHandler;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\TypedData\ListInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\typed_data\Context\ContextDefinition;
use Drupal\typed_data\DataFetcherInterface;

/**
 * Context handler that allows assigning nested context values.
 *
 * @package Drupal\typed_data_context_assignment\Plugin\Context
 */
class ContextHandler extends CoreContextHandler {

  /**
   * The data fetcher service.
   *
   * @var \Drupal\typed_data\DataFetcherInterface
   */
  protected $dataFetcher;

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * ContextHandler constructor.
   *
   * @param \Drupal\typed_data\DataFetcherInterface $data_fetcher
   *   The data fetcher service.
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typed_data_manager
   *   The typed data manager.
   */
  public function __construct(DataFetcherInterface $data_fetcher, TypedDataManagerInterface $typed_data_manager) {
    $this->dataFetcher = $data_fetcher;
    $this->typedDataManager = $typed_data_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getMatchingContexts(array $contexts, ContextDefinitionInterface $definition) {
    return array_filter($contexts, function (ContextInterface $context) use ($definition) {
      return $definition->isSatisfiedBy($context) || $this->definitionIsSatisfiedByAProperty($definition, $context->getContextData());
    });
  }

  /**
   * Determine whether a property of the data can satisfy the context.
   *
   * @param \Drupal\Core\Plugin\Context\ContextDefinitionInterface $definition
   *   The context definition we are trying to satisfy.
   * @param \Drupal\Core\TypedData\TypedDataInterface $typed_data
   *   The typed data available.
   * @param integer $recursion_level
   *   How many layers down we have looked.
   *
   * @return bool
   *   TRUE if the data or one of its properties can satisfy the context.
   */
  protected function definitionIsSatisfiedByAProperty(ContextDefinitionInterface $definition, TypedDataInterface $typed_data, int $recursion_level = 0) {
    // Assume true if we're already 6 levels deep.
    if ($recursion_level > 6) {
      return TRUE;
    }

    $context = new Context(
      ContextDefinition::create($typed_data->getDataDefinition()->getDataType())
        ->setConstraints($typed_data->getDataDefinition()->getConstraints()),
      $typed_data->getValue()
    );
    if ($definition->isSatisfiedBy($context)) {
      return TRUE;
    }

    if ($typed_data->getDataDefinition() instanceof ComplexDataDefinitionInterface) {
      /** @var \Drupal\Core\TypedData\ComplexDataInterface $typed_data */
      foreach ($typed_data->getDataDefinition()->getPropertyDefinitions() as $name => $property_def) {
        try {
          if ($this->definitionIsSatisfiedByAProperty($definition, $typed_data->get($name), $recursion_level + 1)) {
            return TRUE;
          }
        }
        catch (MissingDataException $exception) {
          // Try to continue with no data.
          if ($this->definitionIsSatisfiedByAProperty(
            $definition,
            $this->typedDataManager->create($property_def, NULL, $name, $typed_data),
            $recursion_level +1
          )) {
            return TRUE;
          }
        }
      }
    }
    else if ($typed_data instanceof ListInterface) {
      if ($typed_data->count()) {
        foreach ($typed_data as $item_data) {
          if ($this->definitionIsSatisfiedByAProperty($definition, $item_data, $recursion_level + 1)) {
            return TRUE;
          }
        }
      }
      else {
        if ($this->definitionIsSatisfiedByAProperty(
          $definition,
          $this->typedDataManager->create($typed_data->getItemDefinition(), NULL, 0, $typed_data),
          $recursion_level + 1
        )) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * We copy and paste rather than call the parent here, because by the end of
   * this function we cannot tell whether a context has been satisfied or had
   * it's default value applied. While this might make maintaining the module
   * harder it also makes the logic easier to read.
   */
  public function applyContextMapping(ContextAwarePluginInterface $plugin, $contexts, $mappings = []) {
    /** @var $contexts \Drupal\Core\Plugin\Context\ContextInterface[] */
    $mappings += $plugin->getContextMapping();
    // Loop through each of the expected contexts.

    $missing_value = [];

    foreach ($plugin->getContextDefinitions() as $plugin_context_id => $plugin_context_definition) {
      // If this context was given a specific name, use that.
      [$context_id, $data_path] = $this->parseContextId($mappings[$plugin_context_id] ?? $plugin_context_id, $contexts);

      if (!empty($contexts[$context_id])) {
        // This assignment has been used, remove it.
        unset($mappings[$plugin_context_id]);

        // Plugins have their own context objects, only the value is applied.
        // They also need to know about the cacheability metadata of where that
        // value is coming from, so pass them through to those objects.
        $plugin_context = $plugin->getContext($plugin_context_id);
        if ($plugin_context instanceof ContextInterface && $contexts[$context_id] instanceof CacheableDependencyInterface) {
          $plugin_context->addCacheableDependency($contexts[$context_id]);
        }

        if (empty($data_path)) {
          // Pass the value to the plugin if there is one.
          if ($contexts[$context_id]->hasContextValue()) {
            $plugin->setContext($plugin_context_id, $contexts[$context_id]);
          }
          elseif ($plugin_context_definition->isRequired()) {
            // Collect required contexts that exist but are missing a value.
            $missing_value[] = $plugin_context_id;
          }
        }
        else {
          try {
            $cache_metadata = new BubbleableMetadata();
            $data = $this->dataFetcher->fetchDataByPropertyPath(
              $contexts[$context_id]->getContextData(),
              $data_path,
              $cache_metadata
            );

            $plugin_context->addCacheableDependency($cache_metadata);
            $plugin->setContextValue($plugin_context_id, $data->getValue());

            $new_plugin_context = $plugin->getContext($plugin_context_id);
            if ($new_plugin_context instanceof ContextInterface) {
              $new_plugin_context->addCacheableDependency($cache_metadata);
            }

            if (!$new_plugin_context->hasContextValue() && $plugin_context_definition->isRequired()) {
              // Collect required contexts that exist but are missing a value.
              $missing_value[] = $plugin_context_id;
            }
          }
          catch (MissingDataException $exception) {
            $missing_value[] = $plugin_context_id;
          }
        }

        // Proceed to the next definition.
        continue;
      }

      try {
        $context = $plugin->getContext($context_id);
      }
      catch (ContextException $e) {
        $context = NULL;
      }
        // @todo Remove in https://www.drupal.org/project/drupal/issues/3046342.
      catch (PluginException $e) {
        $context = NULL;
      }

      if ($context && $context->hasContextValue()) {
        // Ignore mappings if the plugin has a value for a missing context.
        unset($mappings[$plugin_context_id]);
        continue;
      }

      if ($plugin_context_definition->isRequired()) {
        // Collect required contexts that are missing.
        $missing_value[] = $plugin_context_id;
        continue;
      }

      // Ignore mappings for optional missing context.
      unset($mappings[$plugin_context_id]);
    }

    // If there are any mappings that were not satisfied, throw an exception.
    // This is a more severe problem than missing values, so check and throw
    // this first.
    if (!empty($mappings)) {
      throw new ContextException('Assigned contexts were not satisfied: ' . implode(',', array_keys($mappings)));
    }

    // If there are any required contexts without a value, throw an exception.
    if ($missing_value) {
      throw new MissingValueContextException($missing_value);
    }
  }

  /**
   * Parse the context id into a context id and data path.
   *
   * @param string $context_id
   *   The context id.
   * @param array $contexts
   *   The available_contexts.
   *
   * @return array
   *   An array where the first item is the context id, and the second item is
   *   the data path.
   */
  protected function parseContextId(string $context_id, array $contexts) : array {
    $bits = explode(':', $context_id);

    $service = '';
    $context_name_and_path = reset($bits);
    if (count($bits) == 2) {
      $service = $bits[0] . ':';
      $context_name_and_path = $bits[1];
    }

    $bits = explode('.', $context_name_and_path);
    $context_name = $service . array_shift($bits);

    while (!isset($contexts[$context_name])) {
      if (empty($bits)) {
        throw new ContextException('Could not find a context for ' . $context_id);
      }

      $context_name .= '.' . array_shift($bits);
    }

    return [$context_name, implode('.', $bits)];
  }

}
