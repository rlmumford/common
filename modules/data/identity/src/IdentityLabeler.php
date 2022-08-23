<?php

namespace Drupal\identity;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\identity\Entity\Identity;
use Drupal\identity\Plugin\IdentityDataClass\LabelingIdentityDataClassInterface;

/**
 * Class IdentityLabeler
 *
 * @package Drupal\identity
 */
class IdentityLabeler implements IdentityLabelerInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The data class manager service.
   *
   * @var \Drupal\identity\IdentityDataClassManager
   */
  protected IdentityDataClassManager $classManager;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * IdentityLabeler constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\identity\IdentityDataClassManager $class_manager
   *   The class manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    IdentityDataClassManager $class_manager,
    CacheBackendInterface $cache,
    Connection $connection
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->classManager = $class_manager;
    $this->cache = $cache;
    $this->database = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function label(Identity $identity, IdentityLabelContext $context = NULL, BubbleableMetadata $bubbleable_metadata = NULL) : ?string {
    $context = $context ?: new IdentityLabelContext();
    $cid = 'identity:'.$identity->id().':label'. $context->getCacheCid();

    if (($cache = $this->cache->get($cid)) && !empty($cache->data)) {
      return $cache->data;
    }
    else {
      $bubbleable_metadata = $bubbleable_metadata ?: new BubbleableMetadata();

      if (!($label = $this->retrieveLabel($identity, $context, $bubbleable_metadata))) {
        $label = $this->buildLabel($identity, $context, $bubbleable_metadata);

        if ($label) {
          $this->storeLabel($label, $identity, $context);
        }
      }

      if ($label) {
        $this->cache->set($cid, $label, $bubbleable_metadata->getCacheMaxAge(), $bubbleable_metadata->getCacheTags());
      }

      return $label;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function labelMultiple(
    array $identities,
    IdentityLabelContext $context = NULL,
    BubbleableMetadata $bubbleable_metadata = NULL
  ): array {
    $bubbleable_metadata = $bubbleable_metadata ?: new BubbleableMetadata();
    $context = $context ?: new IdentityLabelContext();

    // Prepare an array to store the resultant labels.
    $labels = [];

    // Test cache.
    $cids = [];
    foreach ($identities as $key => $identity) {
      $cids[$key] = 'identity:'.$identity->id().':label'. $context->getCacheCid();
    }
    foreach ($this->cache->getMultiple($cids) as $cid => $cached) {
      $labels[array_search($cid, $cids)] = $cached->data;
    }

    // Remove any identities we have been able to load from cache.
    $identities = array_diff_key($identities, $labels);

    // If no identities are left, we're finished.
    if (empty($identities)) {
      return $labels;
    }

    // Try retrieval.
    foreach ($this->retrieveMultipleLabels($identities, $context, $bubbleable_metadata) as $key => $label) {
      $labels[$key] = $label;

      // Set the cache if one is found.
      $this->cache->set(
        "identity:{$identities[$key]->id()}:label{$context->getCacheCid()}",
        $label,
        $bubbleable_metadata->getCacheMaxAge(),
        $bubbleable_metadata->getCacheTags()
      );
    }

    // Remove any identities we have already got labels for.
    $identities = array_diff_key($identities, $labels);

    // With any identites that are left, build the label.
    foreach ($identities as $key => $identity) {
      $cache_metadata = new BubbleableMetadata();
      $labels[$key] = $this->buildLabel($identity, $context, $cache_metadata);
      $this->storeLabel($labels[$key], $identity, $context);

      $this->cache->set(
        "identity:{$identity->id()}:label{$context->getCacheCid()}",
        $labels[$key],
        $cache_metadata->getCacheMaxAge(),
        $cache_metadata->getCacheTags()
      );

      $bubbleable_metadata->merge($cache_metadata);
    }

    return $labels;
  }

  /**
   * Retrieve the label from storage.
   *
   * @param \Drupal\identity\Entity\Identity $identity
   *   The identity being labelled.
   * @param \Drupal\identity\IdentityLabelContext $context
   *   The label context.
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *   The cache metadata.
   *
   * @return string|null
   *   The retrieved label or NULL if none could be found.
   */
  protected function retrieveLabel(Identity $identity, IdentityLabelContext $context, BubbleableMetadata $bubbleable_metadata) : ?string {
    try {
      return $this->database->select('identity_label', 'l')
        ->condition('l.identity', $identity->id())
        ->condition('l.context', $context->getCacheCid())
        ->fields('l', ['label'])
        ->execute()
        ->fetchField();
    }
    catch (\Exception $exception) {
      // @todo log the exception when trying to retrieve the label.
      return NULL;
    }
  }

  /**
   * Retrieve the labels from storage.
   *
   * @param \Drupal\identity\Entity\Identity[] $identities
   *   The identities being labelled.
   * @param \Drupal\identity\IdentityLabelContext $context
   *   The label context.
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *   The cache metadata.
   *
   * @return string[]
   *   The labels keyed by the keys of $identities.
   */
  protected function retrieveMultipleLabels(array $identities, IdentityLabelContext $context, BubbleableMetadata $bubbleable_metadata) : array {
    $ids = [];
    foreach ($identities as $key => $identity) {
      $ids[$identity->id()] = $key;
    }

    $result = $this->database->select('identity_label', 'l')
      ->fields('l', ['identity', 'label'])
      ->condition('identity', array_keys($ids), 'IN')
      ->condition('context', $context->getCacheCid())
      ->execute()
      ->fetchAllKeyed();

    $labels = [];
    foreach ($result as $id => $label) {
      $labels[$ids[$id]] = $label;
    }
    return $labels;
  }

  /**
   * Store the label.
   *
   * @param string $label
   *   The label to store.
   * @param \Drupal\identity\Entity\Identity $identity
   *   The identity to store the label against.
   * @param \Drupal\identity\IdentityLabelContext $context
   *   The context the label was generated for.
   *
   * @return void
   */
  protected function storeLabel(string $label, Identity $identity, IdentityLabelContext $context) : void {
    $this->database->merge('identity_label')
      ->keys([
        'identity' => $identity->id(),
        'context' => $context->getCacheCid(),
      ])
      ->fields([
        'label' => $label,
      ])
      ->execute();
  }

  /**
   * @param \Drupal\identity\Entity\Identity $identity
   * @param \Drupal\identity\IdentityLabelContext $context
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *
   * @return null|string
   */
  protected function buildLabel(Identity $identity, IdentityLabelContext $context, BubbleableMetadata $bubbleable_metadata) {
    /** @var \Drupal\identity\Plugin\IdentityDataClass\LabelingIdentityDataClassInterface[] $label_providing_classes */
    $label_providing_classes = [];
    foreach ($this->classManager->getDefinitions() as $class => $definition) {
      $plugin = $this->classManager->createInstance($class);

      if ($plugin instanceof LabelingIdentityDataClassInterface) {
        $label_providing_classes[] = $plugin;
      }
    }

    usort(
      $label_providing_classes,
      function (
        LabelingIdentityDataClassInterface $a,
        LabelingIdentityDataClassInterface $b
      ) use ($identity, $context, $bubbleable_metadata) {
        return $a->identityLabelPriority($identity, $context, $bubbleable_metadata) > $b->identitylabelPriority($identity, $context, $bubbleable_metadata) ? -1 : 1;
      }
    );

    $label = NULL;
    foreach ($label_providing_classes as $label_providing_class) {
      $label = $label_providing_class->identityLabel($identity, $context, $bubbleable_metadata);

      if ($label) {
        break;
      }
    }

    // @todo: Label altering pass?
    // @todo: Event?

    return $label;
  }

}
