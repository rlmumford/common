<?php

namespace Drupal\identity;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\identity\Entity\IdentityDataInterface;
use Drupal\identity\Event\IdentityEvents;
use Drupal\identity\Event\PreAcquisitionEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\Serializer;

/**
 * Class IdentityDataIdentityAcquirer
 *
 * @package Drupal\identity
 */
class IdentityDataIdentityAcquirer implements IdentityDataIdentityAcquirerInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * IdentityDataIdentityAcquirer constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EventDispatcherInterface $event_dispatcher,
    Connection $connection,
    Serializer $serializer,
    QueueFactory $queue
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->connection = $connection;
    $this->serializer = $serializer;
    $this->queue = $queue->get('identity_acquisition');
  }

  /**
   * Log the start of an acquisition.
   *
   * @param $acquisition_id
   *
   * @throws \Exception
   */
  public function logStart($acquisition_id) {
    $this->connection->merge('identity_acquisition')
      ->key('acquisition_id', $acquisition_id)
      ->insertFields([
        'requested' => (new DrupalDateTime())->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'user' => \Drupal::currentUser()->id(),
      ])
      ->fields([
        'started' => (new DrupalDateTime())->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ])
      ->execute();
  }

  /**
   * Log that an acquisition is completed.
   *
   * @param $acquisition_id
   * @param \Drupal\identity\IdentityAcquisitionResult $result
   *
   * @throws \Exception
   */
  public function logCompleted($acquisition_id, IdentityAcquisitionResult $result) {
    $this->connection->merge('identity_acquisition')
      ->key('acquisition_id', $acquisition_id)
      ->fields([
        'completed' => (new DrupalDateTime())->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'identity' => $result->getIdentity()->id(),
        'method' => $result->getMethod(),
        'data' => $this->serializer->normalize($result),
      ])
      ->execute();
  }

  /**
   * Log that an acquisition is requested.
   *
   * @param $acquisition_id
   *
   * @throws \Exception
   */
  public function logRequested($acquisition_id) {
    $this->connection->merge('identity_acquisition')
      ->key('acquisition_id', $acquisition_id)
      ->fields([
        'requested' => (new DrupalDateTime())->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'user' => \Drupal::currentUser()->id(),
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function acquireIdentity(IdentityDataGroup $data_group, array $options = []) {
    if (!empty($options['force_now'])) {
      $this->logStart($data_group->getId());
      $result = $this->doAcquireIdentity($data_group, $options);
      $this->logCompleted($data_group->getId(), $result);

      return $result;
    }

    $this->logRequested($data_group->getId());
    return $this->queueAcquireIdentity($data_group, $options);
  }

  /**
   * Queue an identity data acquisition.
   *
   * @param \Drupal\identity\IdentityDataGroup $data_group
   * @param array $options
   */
  protected function queueAcquireIdentity(IdentityDataGroup $data_group, array $options = []) {
    $this->queue->createItem([
      'group' => $data_group,
      'options' => $options,
    ]);

    return new IdentityAcquisitionResult(NULL, IdentityAcquisitionResult::METHOD_QUEUED);
  }

  /**
   * Acquire an identity for the
   *
   * @param \Drupal\identity\IdentityDataGroup $data_group
   * @param array $options
   *   Options governing this acquisition process.
   *
   * @return \Drupal\identity\IdentityAcquisitionResult
   */
  protected function doAcquireIdentity(IdentityDataGroup $data_group, array $options = []) {
    // Look for a matching source.
    if (($source = $data_group->getSource()) && $source->reference->value) {
      $source_storage = $this->entityTypeManager->getStorage('identity_data_source');

      $ids = $source_storage
        ->getQuery()
        ->condition('reference', $source->reference->value)
        ->range(0, 1)
        ->execute();
      if ($ids) {
        $data_group->setSource($source_storage->load(reset($ids)));
      }
    }

    // Look for matching references.
    /** @var \Drupal\identity\Entity\IdentityData[] $refs */
    $refs = [];
    foreach ($data_group->getDatas() as $data) {
      if (!$data->reference->isEmpty() && $data->reference->value) {
        $refs[$data->reference->value] = $data;
      }
    }
    if (!empty($refs)) {
      $data_storage = $this->entityTypeManager->getStorage('identity_data');
      $existing = $data_storage->getQuery()
        ->condition('reference', array_keys($refs), 'IN')
        ->execute();

      if (!empty($existing)) {
        $existing_id = NULL;
        foreach ($data_storage->loadMultiple($existing) as $existing_data) {
          // Set the id, vid and uuid of the submitted data so that it counts
          // as an update.
          if ($submitted_data = $refs[$existing_data->reference->value]) {
            $submitted_data->id = $existing_data->id();
            $submitted_data->uuid = $existing_data->uuid->value;
            $submitted_data->enforceIsNew(FALSE);

            if ($existing_data->getIdentityId()) {
              if (is_null($existing_id) || $existing_id == $existing_data->getIdentityId()) {
                $existing_id = $existing_data->getIdentityId();
              }
              else {
                $existing_id = FALSE;
                // @todo: This means that there are existing items that match
                // multiple identities. I'm not sure what we would do in this
                // maybe reaquire everything
              }
            }

            unset($refs[$existing_data->reference->value]);
          }
        }

        if ($existing_id && empty($options['force_reacquire'])) {
          return new IdentityAcquisitionResult(
            $this->entityTypeManager->getStorage('identity')->load($existing_id),
            IdentityAcquisitionResult::METHOD_REFERENCE
          );
        }
      }
    }

    // Allow modules to massage data in the group
    $event = new PreAcquisitionEvent($data_group);
    $this->eventDispatcher->dispatch(IdentityEvents::PRE_ACQUISITION, $event);

    // Order datas by their acquisition priority.
    $datas = $data_group->getDatas();
    usort($datas, function(IdentityDataInterface $a, IdentityDataInterface $b) {
      return $a->acquisitionPriority() > $b->acquisitionPriority() ? -1 : 1;
    });

    $ordered_datas = $datas;

    /** @var \Drupal\identity\IdentityMatch[] $all_matches */
    $all_matches = [];

    // Keep track of whether a match is fully supported from each data. Saves
    // time later. Once a search data has fully supported we don't need to keep
    // processing data.
    // This puts a cap on repeated data in the database skewing acquisition
    // results
    // @todo: The same thing for oppostion.
    $fully_supported = [];
    foreach ($ordered_datas as $search_data) {
      // Find all matching parties
      $matches = $search_data->findMatches();

      foreach ($matches as $data_match) {
        $fully_supported += [
          $data_match->getIdentityId() => [],
        ];

        // Get the supporting and opposing datas
        $supporting_datas = $data_match->getSupportingDatas();
        $opposing_datas = $data_match->getOpposingDatas();

        // If we already have a match for this identity, count this found match
        // as a support.
        if (isset($all_matches[$data_match->getIdentityId()])) {
          if ($supporting_match = reset($supporting_datas)) {
            if (
              $all_matches[$data_match->getIdentityId()]->supportMatch(
                $search_data,
                reset($supporting_match['match_data']),
                $supporting_match['effect'],
                $supporting_match['level']
              )
            ) {
              $fully_supported[$data_match->getIdentityId()][$search_data->uuid()] = TRUE;
            }
          }
          else if ($opposing_match = reset($opposing_datas)) {
            $all_matches[$data_match->getIdentityId()]->opposeMatch($search_data, reset($opposing_match['match_data']), $opposing_match['effect'], $opposing_match['level']);
          }
        }
        else {
          $all_matches[$data_match->getIdentityId()] = $data_match;

          // Compute whether this is fully supported or not.
          $supporting_match = reset($supporting_datas);
          $possible_levels = $search_data->possibleMatchSupportLevels();
          if (empty($possible_levels) || (count(array_intersect($possible_levels, $supporting_match['level'])) === count($possible_levels))) {
            $fully_supported[$data_match->getIdentityId()][$search_data->uuid()] = TRUE;
          }
        }
      }
    }

    foreach ($all_matches as $identity_id => $identity_match) {
      foreach ($ordered_datas as $search_data) {
        if (empty($fully_supported[$identity_id][$search_data->uuid()])) {
          $search_data->supportOrOppose($identity_match);
        }
      }

      if ($identity_match->isSufficient()) {
        return new IdentityAcquisitionResult($identity_match->getIdentity(), IdentityAcquisitionResult::METHOD_FOUND);
      }
    }

    /** @var \Drupal\identity\IdentityMatch $top_match */
    $top_match = NULL;
    foreach ($all_matches as $match) {
      if ($match->isSufficient() && (empty($top_match) || $match->getScore() > $top_match->getScore())) {
        $top_match = $match;
      }
    }

    if ($top_match) {
      return new IdentityAcquisitionResult($top_match->getIdentity(), IdentityAcquisitionResult::METHOD_FOUND, $all_matches);
    }
    else {
      /** @var \Drupal\identity\Entity\Identity $identity */
      $identity = $this->entityTypeManager->getStorage('identity')->create();
      return new IdentityAcquisitionResult($identity, IdentityAcquisitionResult::METHOD_CREATE, $all_matches);
    }
  }

}
