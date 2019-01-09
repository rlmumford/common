<?php

namespace Drupal\ebids\Storage;

use Aws\DynamoDb\Marshaler;
use Drupal\ebids\EventInterface;

class AmazonDynamoDbEventMarshaler extends Marshaler {

  /**
   * Marshal an event.
   *
   * @param EventInterface $event
   *
   * @return array
   */
  public function marshalEvent(EventInterface $event) {
    return $this->marshalItem(array());
  }

  /**
   * Convert a response from AmazonDynamoDb to a Event entity.
   *
   * @param array $item
   * @param $event_class
   */
  public function unmarshalEvent(array $item, $event_class) {

  }



}
