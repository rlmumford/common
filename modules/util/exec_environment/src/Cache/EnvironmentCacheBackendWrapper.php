<?php

namespace Drupal\exec_environment\Cache;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\exec_environment\EnvironmentStackInterface;
use Drupal\exec_environment\Plugin\ExecEnvironment\Component\CacheBinSuffixComponentInterface;

/**
 * This cache backend wraps another and applies an environment suffix to the
 * front of each items cid.
 *
 * @package Drupal\exec_environment\Cache
 */
class EnvironmentCacheBackendWrapper implements CacheBackendInterface, CacheTagsInvalidatorInterface {

  /**
   * The wrapped backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $wrappedBackend;

  /**
   * The environment stack.
   *
   * @var \Drupal\exec_environment\EnvironmentStackInterface
   */
  protected $environmentStack;

  /**
   * The bin name.
   *
   * @var string
   */
  protected $bin;

  /**
   * EnvironmentCacheBackendWrapper constructor.
   *
   * @param \Drupal\exec_environment\EnvironmentStackInterface $environment_stack
   *   The environment stack.
   * @param \Drupal\Core\Cache\CacheBackendInterface $wrapped_backend
   *   The cache backend to wrap.
   * @param string $bin
   *   The bin name.
   */
  public function __construct(EnvironmentStackInterface $environment_stack, CacheBackendInterface $wrapped_backend, string $bin) {
    $this->wrappedBackend = $wrapped_backend;
    $this->environmentStack = $environment_stack;
    $this->bin = $bin;
  }

  /**
   * Get the environment bin suffixes as a cid prefix.
   *
   * @return string
   *   The cid prefix.
   */
  protected function getBinSuffixesAsPrefix() {
    $suffixes = [];
    /** @var \Drupal\exec_environment\Plugin\ExecEnvironment\Component\CacheBinSuffixComponentInterface $component */
    foreach ($this->environmentStack->getActiveEnvironment()->getComponents(CacheBinSuffixComponentInterface::class) as $component) {
      if ($suffix = $component->getCacheBinSuffixPart($this->bin)) {
        $suffixes[] = $suffix;
      }
    }

    if (!empty($suffixes)) {
      sort($suffixes);
      return "exenv_" . implode("_", $suffixes) . '.';
    }

    return '';
  }

  /**
   * Map a set of cids.
   *
   * @param string[] $cids
   *   The cids.
   *
   * @return string[]
   *   The mapped cids.
   */
  protected function mapCids(array &$cids) {
    if ($prefix = $this->getBinSuffixesAsPrefix()) {
      $cids = array_map(function($cid) use ($prefix) {
        return $prefix . $cid;
      }, $cids);
    }

    return $cids;
  }

  /**
   * {@inheritdoc}
   */
  public function get($cid, $allow_invalid = FALSE) {
    return $this->wrappedBackend->get($this->getBinSuffixesAsPrefix() . $cid, $allow_invalid);
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    $this->mapCids($cids);
    return $this->wrappedBackend->getMultiple($cids, $allow_invalid);
  }

  /**
   * {@inheritdoc}
   */
  public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = []) {
    $this->wrappedBackend->set($this->getBinSuffixesAsPrefix() . $cid, $data, $expire, $tags);
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $items) {
    if ($prefix = $this->getBinSuffixesAsPrefix()) {
      $real_items = [];
      foreach ($items as $cid => $item) {
        $real_items[$prefix . $cid] = $item;
      }
      $items = $real_items;
    }
    $this->wrappedBackend->setMultiple($items);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($cid) {
    $this->wrappedBackend->delete($this->getBinSuffixesAsPrefix() . $cid);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids) {
    $this->wrappedBackend->deleteMultiple($this->mapCids($cids));
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    $this->wrappedBackend->deleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate($cid) {
    $this->wrappedBackend->invalidate($this->getBinSuffixesAsPrefix() . $cid);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $tags) {
    if ($this->wrappedBackend instanceof CacheTagsInvalidatorInterface) {
      $this->wrappedBackend->invalidateTags($tags);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateMultiple(array $cids) {
    $this->wrappedBackend->invalidateMultiple($this->mapCids($cids));
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateAll() {
    $this->wrappedBackend->invalidateAll();
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    $this->wrappedBackend->garbageCollection();
  }

  /**
   * {@inheritdoc}
   */
  public function removeBin() {
    $this->wrappedBackend->removeBin();
  }

}
