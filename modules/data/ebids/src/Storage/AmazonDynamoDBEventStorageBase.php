<?php

namespace Drupal\ebids\Storage;

use Aws\DynamoDb\Marshaler;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\ebids\AwsSdk;
use Drupal\ebids\EventInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AmazonDynamoDBEventStorageBase extends PluginBase implements EventStorageInterface, ContainerFactoryPluginInterface {

  /**
   * The dynamo db client.
   *
   * @var \Aws\DynamoDb\DynamoDbClient
   */
  protected $dynamodb;

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('ebids.aws.sdk'));
  }

  /**
   * AmazonDynamoDBEventStorageBase constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AwsSdk $sdk) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->dynamodb = $sdk->createDynamoDb();
  }

  /**
   * Get the table name of the table these events are stored in.
   */
  protected function getTableName() {
    return $this->configuration['table'];
  }

  /**
   * Marshal an event into dynamo db format.
   *
   * @param \Drupal\ebids\EventInterface $event
   *   The event to marshal.
   *
   * @return array
   *   An array in dynamo db format.
   */
  protected function marshalEvent(EventInterface $event) {
    $marshaler = new AmazonDynamoDbEventMarshaler();
    return $marshaler->marshalEvent($event);
  }

  /**
   * Record an event in this storage.
   *
   * @param \Drupal\ebids\EventInterface $event
   *
   * @return static
   */
  public function recordEvent(EventInterface $event) {
    $this->dynamodb->putItem([
      'TableName' => $this->getTableName(),
      'Item' => $this->marshalEvent($event),
    ]);

    return $this;
  }

  /**
   * Read a specific event in this storage.
   *
   * @param string $uuid
   *   The uuid of the event to return.
   * @param string $return_class
   *   The class of object to return, must implement EventInterface.
   *
   * @return \Drupal\ebids\EventInterface
   */
  public function readEvent($uuid, $return_class = NULL) {
    $marshaler = new AmazonDynamoDbEventMarshaler();
    $key = [
      'uuid' => $uuid,
    ];
    $result = $this->dynamodb->getItem([
      'TableName' => $this->getTableName(),
      'Key' => $marshaler->marshalItem($key),
    ]);

    if (!empty($result['Item'])) {
      return $marshaler->unmarshalEvent($result['Item'], $return_class);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function readEvents(array $ids = array(), $return_class = NULL) {
    // TODO: Implement readEvents() method.
  }

  /**
   * Find events based on a query.
   *
   * @param array $query
   *   The query to filter events by.
   * @param string $return_class
   *   The class of objects to return must implement EventInterface.
   *
   * @return \Drupal\ebids\EventInterface[]
   */
  public function findEvents(array $query, $return_class = NULL) {
    // TODO: Implement findEvents() method.
  }
}
