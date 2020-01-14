<?php

namespace Drupal\identity;
use Drupal\Core\Cache\CacheBackendInterface;
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
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\identity\IdentityDataClassManager
   */
  protected $classManager;

  /**
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * IdentityLabeler constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\identity\IdentityDataClassManager $class_manager
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    IdentityDataClassManager $class_manager,
    CacheBackendInterface $cache
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->classManager = $class_manager;
    $this->cache = $cache;
  }

  /**
   * Get the label for the identity.
   *
   * @param \Drupal\identity\Entity\Identity $identity
   * @param \Drupal\identity\IdentityLabelContext|NULL $context
   *
   * @return string
   */
  public function label(Identity $identity, IdentityLabelContext $context = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {
    $context = $context ?: new IdentityLabelContext();
    $cid = 'identity:'.$identity->id().':label'. $context->getCacheCid();

    if (($cache = $this->cache->get($cid)) && !empty($cache->data)) {
      return $cache->data;
    }
    else {
      $bubbleable_metadata = $bubbleable_metadata ?: new BubbleableMetadata();
      $label = $this->buildLabel($identity, $context, $bubbleable_metadata);
      $this->cache->set($cid, $label, $bubbleable_metadata->getCacheMaxAge(), $bubbleable_metadata->getCacheTags());

      return $label;
    }
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
      dpm($label_providing_class, 'Class');
      $label = $label_providing_class->identityLabel($identity, $context, $bubbleable_metadata);
      dpm($label);

      if ($label) {
        break;
      }
    }

    // @todo: Label altering pass?
    // @todo: Event?

    return $label;
  }

}
