<?php

namespace Drupal\ebids\Storage;

use Aws\DynamoDb\Marshaler;
use Drupal\ebids\EventInterface;

class AmazonDynamoDbEventMarshaler extends Marshaler {

  /**
   * Marshal an event.
   */
  public function marshalEvent(EventInterface $event) {
    return $this->marshalItem(array());
  }

}
