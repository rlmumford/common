<?php

declare(strict_types=1);

namespace Drupal\typed_data_plus;

use Drupal\typed_data\DataFilterInterface;

/**
 * A filter that can request a typed object instead of its raw value.
 */
interface WrappedValueFilterInterface extends DataFilterInterface {

  /**
   * Whether filter() needs the current TypedDataInterface object.
   */
  public function usesWrappedValue(): bool;

}
