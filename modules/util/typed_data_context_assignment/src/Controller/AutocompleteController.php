<?php

namespace Drupal\typed_data_context_assignment\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\typed_data\DataFetcherInterface;
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
   * @var \Drupal\typed_data\DataFetcherInterface
   */
  protected $dataFetcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('typed_data.data_fetcher'),
      $container->get('keyvalue')->get('typed_data_context_assignment_autocomplete')
    );
  }

  /**
   * AutocompleteController constructor.
   *
   * @param \Drupal\typed_data\DataFetcherInterface $data_fetcher
   *   The data fetcher service.
   * @param \Drupal\Core\KeyValueStore\KeyValueStoreInterface $key_value
   *   The key value store.
   */
  public function __construct(DataFetcherInterface $data_fetcher, KeyValueStoreInterface $key_value) {
    $this->dataFetcher = $data_fetcher;
    $this->keyValue = $key_value;
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
   *
   * @todo Work out how to filter by permitted data types.
   */
  public function handleAutocomplete(Request $request, string $required_context_key, string $available_context_key) {
    if (!$this->keyValue->has($required_context_key) || !$this->keyValue->has($available_context_key)) {
      throw new NotFoundHttpException();
    }

    $required_context = $this->keyValue->get($required_context_key);
    $available_context = $this->keyValue->get($available_context_key);

    $definitions = [];
    foreach ($available_context as $name => $definition) {
      $definitions[$name] = $definition->getDataDefinition();
    }

    // The include filters flag is provided by MR1 on the typed data module.
    // See https://www.drupal.org/project/typed_data/issues/3244608
    $results = $this->dataFetcher->autocompletePropertyPath($definitions, $request->query->get('q'), TRUE);
    return new JsonResponse($results);

  }

}
