<?php

declare(strict_types=1);

namespace Drupal\Tests\typed_data_plus\Kernel;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\TypedData\Plugin\DataType\StringData;

/**
 * Typed context fixture with access-sensitive cache metadata.
 */
class CacheableConditionData extends StringData implements CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['user.permissions'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return ['example:1'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 30;
  }

}
