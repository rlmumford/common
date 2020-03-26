<?php

namespace Drupal\identity\Plugin\QueueWorker;

use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\identity\IdentityDataIdentityAcquirerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class IdentityAcquisitionQueueWorker
 *
 * @QueueWorker(
 *   id = "identity_acquisition",
 *   title = @Translation("Identity Acquisition Queue Worker"),
 *   cron = {"time" = 60}
 * )
 *
 * @package Drupal\identity\Plugin\QueueWorker
 */
class IdentityAcquisitionQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\identity\IdentityDataIdentityAcquirerInterface
   */
  protected $acquirer;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('identity.acquirer'),
      $container->get('database')
    );
  }

  /**
   * IdentityAcquisitionQueueWorker constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\identity\IdentityDataIdentityAcquirerInterface $acquirer
   * @param \Drupal\Core\Database\Connection $connection
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    IdentityDataIdentityAcquirerInterface $acquirer,
    Connection $connection
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->acquirer = $acquirer;
    $this->connection = $connection;
  }

  /**
   * Works on a single queue item.
   *
   * @param mixed $data
   *   The data that was passed to
   *   \Drupal\Core\Queue\QueueInterface::createItem() when the item was queued.
   *
   * @throws \Drupal\Core\Queue\RequeueException
   *   Processing is not yet finished. This will allow another process to claim
   *   the item immediately.
   * @throws \Exception
   *   A QueueWorker plugin may throw an exception to indicate there was a
   *   problem. The cron process will log the exception, and leave the item in
   *   the queue to be processed again later.
   * @throws \Drupal\Core\Queue\SuspendQueueException
   *   More specifically, a SuspendQueueException should be thrown when a
   *   QueueWorker plugin is aware that the problem will affect all subsequent
   *   workers of its queue. For example, a callback that makes HTTP requests
   *   may find that the remote server is not responding. The cron process will
   *   behave as with a normal Exception, and in addition will not attempt to
   *   process further items from the current item's queue during the current
   *   cron run.
   *
   * @see \Drupal\Core\Cron::processQueues()
   */
  public function processItem($data) {
    /** @var \Drupal\identity\IdentityDataGroup $group */
    $group = $data['group'];
    $this->acquirer->acquireIdentity($group, $data['options']);
  }

}
