<?php

namespace Drupal\typed_data_context_assignment\Plugin\Context;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Plugin\Exception\MissingValueContextException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\Core\Plugin\Context\ContextHandler as CoreContextHandler;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
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
      return $definition->isSatisfiedBy($context) || $this->definitionIsSatisfiedByAnyProperty($definition, $context->getContextData());
    });
  }

  /**
   * Determine whether a property of the data can satisfy the context.
   *
   * @param \Drupal\Core\Plugin\Context\ContextDefinitionInterface $definition
   *   The context definition we are trying to satisfy.
   * @param \Drupal\Core\TypedData\TypedDataInterface $typed_data
   *   The typed data available.
   * @param int $recursion_level
   *   How many layers down we have looked.
   *
   * @return bool
   *   TRUE if the data or one of its properties can satisfy the context.
   */
  protected function definitionIsSatisfiedByAnyProperty(ContextDefinitionInterface $definition, TypedDataInterface $typed_data, int $recursion_level = 0) {
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
          if ($this->definitionIsSatisfiedByAnyProperty($definition, $typed_data->get($name), $recursion_level + 1)) {
            return TRUE;
          }
        }
        catch (MissingDataException $exception) {
          // Try to continue with no data.
          if ($this->definitionIsSatisfiedByAnyProperty(
            $definition,
            $this->typedDataManager->create($property_def, NULL, $name, $typed_data),
            $recursion_level + 1
          )) {
            return TRUE;
          }
        }
        catch (\InvalidArgumentException $exception) {
          // Property is unknown. Do nothing.
        }
      }
    }
    elseif ($typed_data instanceof ListInterface) {
      if ($typed_data->count()) {
        foreach ($typed_data as $item_data) {
          if ($this->definitionIsSatisfiedByAnyProperty($definition, $item_data, $recursion_level + 1)) {
            return TRUE;
          }
        }
      }
      elseif ($this->definitionIsSatisfiedByAnyProperty(
        $definition,
        $this->typedDataManager->create($typed_data->getItemDefinition(), NULL, 0, $typed_data),
        $recursion_level + 1
      )) {
        return TRUE;
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
    /** @var \Drupal\Core\Plugin\Context\ContextInterface[] $contexts  */
    $mappings += $plugin->getContextMapping();
    $missing_value = [];

    // Loop through each of the expected contexts.
    foreach ($plugin->getContextDefinitions() as $plugin_context_id => $plugin_context_definition) {
      try {
        // If this context was given a specific name, use that.
        [$context_id, $data_path] = $this->parseContextId(
          $mappings[$plugin_context_id] ?? $plugin_context_id,
          $contexts
        );

        if (is_callable([$this->dataFetcher, 'applyFilters'])) {
          [$path, $filters] = $this->dataFetcher->parsePropertyPathAndFilters($data_path);
        }
        else {
          $path = explode('.', $data_path);
          $filters = [];
        }
      }
      catch (ContextException $e) {
        $context_id = $plugin_context_id;
        $path = $filters = [];
      }

      if ($context_id && !empty($contexts[$context_id])) {
        // This assignment has been used, remove it.
        unset($mappings[$plugin_context_id]);

        // Plugins have their own context objects, only the value is applied.
        // They also need to know about the cacheability metadata of where that
        // value is coming from, so pass them through to those objects.
        $plugin_context = $plugin->getContext($plugin_context_id);
        if ($plugin_context instanceof ContextInterface && $contexts[$context_id] instanceof CacheableDependencyInterface) {
          $plugin_context->addCacheableDependency($contexts[$context_id]);
        }

        if (empty($path)) {
          // Pass the value to the plugin if there is one.
          if ($contexts[$context_id]->hasContextValue()) {
            if (empty($filters)) {
              $plugin->setContext($plugin_context_id, $contexts[$context_id]);
            }
            else {
              try {
                $data = $this->dataFetcher->applyFilters(
                  $contexts[$context_id]->getContextData(),
                  $filters
                );
                if ($data && $data->getValue()) {
                  $old_context_def = $plugin->getContext($plugin_context_id)->getContextDefinition();
                  $new_def_class = strpos($data->getDataDefinition()->getDataType(), 'entity:') !== 0 ? ContextDefinition::class : EntityContextDefinition::class;
                  $plugin->setContext($plugin_context_id, new Context(
                    (new $new_def_class(
                      $data->getDataDefinition()->getDataType(),
                      $old_context_def->getLabel(),
                      $old_context_def->isRequired(),
                      $old_context_def->isMultiple(),
                      $old_context_def->getDescription(),
                      $old_context_def->getDefaultValue(),
                    ))->setConstraints(array_merge($old_context_def->getConstraints(), $data->getDataDefinition()->getConstraints())),
                    $data->getValue()
                  ));
                }
                elseif ($plugin_context_definition->isRequired()) {
                  $missing_value[] = $plugin_context_id;
                }
              }
              catch (MissingDataException $exception) {
                if ($plugin_context_definition->isRequired()) {
                  $missing_value[] = $plugin_context_id;
                }
              }
            }
          }
          elseif ($plugin_context_definition->isRequired()) {
            // Collect required contexts that exist but are missing a value.
            $missing_value[] = $plugin_context_id;
          }
        }
        else {
          try {
            $cache_metadata = new BubbleableMetadata();
            $data = $this->dataFetcher->fetchDataBySubPaths(
              $contexts[$context_id]->getContextData(),
              $path,
              $cache_metadata
            );
            if ($filters) {
              $data = $this->dataFetcher->applyFilters($data, $filters, $cache_metadata);
            }

            $plugin_context->addCacheableDependency($cache_metadata);
            $old_context_def = $plugin->getContextDefinition($plugin_context_id);
            $new_def_class = strpos($data->getDataDefinition()->getDataType(), 'entity:') !== 0 ? ContextDefinition::class : EntityContextDefinition::class;
            $plugin->setContext($plugin_context_id, new Context(
              (new $new_def_class(
                $data->getDataDefinition()->getDataType(),
                $old_context_def->getLabel(),
                $old_context_def->isRequired(),
                $old_context_def->isMultiple(),
                $old_context_def->getDescription(),
                $old_context_def->getDefaultValue(),
              ))->setConstraints(array_merge($old_context_def->getConstraints(), $data->getDataDefinition()->getConstraints())),
              $data->getValue()
            ));

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
    $bits = explode(':', $context_id, 2);

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

  /**
   * {@inheritdoc}
   */
  public function getContextAssignmentElement(ContextAwarePluginInterface $plugin, array $contexts) {
    $assignments = $plugin->getContextMapping();

    $element = ['#tree' => TRUE];
    foreach ($plugin->getContextDefinitions() as $context_slot => $definition) {
      $valid_contexts = $this->getMatchingContexts($contexts, $definition);

      $key_value_storage = \Drupal::keyValue('typed_data_context_assignment_autocomplete');
      $data = serialize($definition);
      $required_context_key = Crypt::hmacBase64($data, Settings::getHashSalt());
      $key_value_storage->set($required_context_key, $definition);

      $available_definitions = [];
      foreach ($valid_contexts as $name => $context) {
        $available_definitions[$name] = $context->getContextDefinition();
      }
      $available_context_key = Crypt::hmacBase64(serialize($available_definitions), Settings::getHashSalt());
      $key_value_storage->set($available_context_key, $available_definitions);

      $element[$context_slot] = [
        '#title' => $definition->getLabel() ?: new TranslatableMarkup('Select a @context value:', ['@context' => $context_slot]),
        '#type' => 'textfield',
        '#description' => $definition->getDescription(),
        '#required' => $definition->isRequired(),
        '#default_value' => !empty($assignments[$context_slot]) ? $assignments[$context_slot] : '',
        '#autocomplete_route_name' => 'typed_data_context_assignment.data_select_autocomplete',
        '#autocomplete_route_parameters' => [
          'required_context_key' => $required_context_key,
          'available_context_key' => $available_context_key,
        ],
      ];
    }

    return $element;
  }

}
