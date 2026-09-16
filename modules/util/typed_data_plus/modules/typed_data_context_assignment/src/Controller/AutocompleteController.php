<?php

namespace Drupal\typed_data_context_assignment\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\typed_data_plus\Plugin\Context\DataContextDefinition;
use Drupal\typed_data_plus\DataFetcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for autocompleting data paths.
 *
 * @package Drupal\typed_data_context_assignment\Controller
 */
class AutocompleteController extends ControllerBase {

  /**
   * The data fetcher.
   *
   * @var \Drupal\typed_data_plus\DataFetcherInterface
   */
  protected $dataFetcher;

  /**
   * Storage for autocomplete definition sets.
   */
  protected KeyValueStoreInterface $autocompleteStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('typed_data_plus.data_fetcher'),
      $container->get('keyvalue')->get('typed_data_context_assignment_autocomplete')
    );
  }

  /**
   * AutocompleteController constructor.
   *
   * @param \Drupal\typed_data_plus\DataFetcherInterface $data_fetcher
   *   The data fetcher service.
   * @param \Drupal\Core\KeyValueStore\KeyValueStoreInterface $key_value
   *   The key value store.
   */
  public function __construct(DataFetcherInterface $data_fetcher, KeyValueStoreInterface $key_value) {
    $this->dataFetcher = $data_fetcher;
    $this->autocompleteStorage = $key_value;
  }

  /**
   * Autocomplete for data selection.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $required_context_key
   *   The required context key.
   * @param string $available_context_key
   *   The available context key.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The json response.
   */
  public function handleAutocomplete(Request $request, string $required_context_key, string $available_context_key) {
    if (!$this->autocompleteStorage->has($required_context_key) || !$this->autocompleteStorage->has($available_context_key)) {
      throw new NotFoundHttpException();
    }

    $required_context = $this->autocompleteStorage->get($required_context_key);
    $available_context = $this->autocompleteStorage->get($available_context_key);

    $definitions = [];
    foreach ($available_context as $name => $definition) {
      $definitions[$name] = $definition->getDataDefinition();
    }

    $query = (string) $request->query->get('q', '');
    $results = [];
    // Global IDs can contain dots. Resolve the longest advertised root before
    // asking the data fetcher to autocomplete the remaining property path.
    uksort($definitions, static fn(string $a, string $b) => strlen($b) <=> strlen($a));
    foreach ($definitions as $name => $definition) {
      if (str_starts_with($query, $name . '.')) {
        $suggestions = $this->dataFetcher->autocompletePropertyPath(['data' => $definition], 'data' . substr($query, strlen($name)));
        foreach ($suggestions as $suggestion) {
          $path = trim(substr($suggestion['value'], 4), '.');
          $fetched = $this->dataFetcher->fetchFilteredDefinition($definition, $path);
          $candidate = new Context(DataContextDefinition::fromDataDefinition($fetched));
          if (str_ends_with($suggestion['value'], '.') || ($required_context->isMultiple() === $candidate->getContextDefinition()->isMultiple() && $required_context->isSatisfiedBy($candidate))) {
            $suggestion['value'] = $name . substr($suggestion['value'], 4);
            $results[] = $suggestion;
          }
        }
        break;
      }
      if (str_starts_with($name, $query)) {
        // Include navigable roots even when their children provide the value.
        $results[] = ['value' => $name, 'label' => $name];
      }
    }
    return new JsonResponse($results);

  }

}
