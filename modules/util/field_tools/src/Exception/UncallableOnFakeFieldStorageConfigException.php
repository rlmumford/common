<?php

namespace Drupal\field_tools\Exception;

class UncallableOnFakeFieldStorageConfigException extends \Exception {

  /**
   * Create from a method name.
   *
   * @param $method
   *
   * @return \Drupal\field_tools\Exception\UncallableOnFakeFieldStorageConfigException
   */
  public static function createFromMethod($method) {
    return new static(
      'Cannot call method '.$method.'() on FakeFieldStorageConfig.'
    );
  }
}
