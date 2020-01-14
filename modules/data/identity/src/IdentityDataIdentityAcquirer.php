<?php

namespace Drupal\identity;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\identity\Entity\IdentityDataInterface;
use Drupal\identity\Event\IdentityEvents;
use Drupal\identity\Event\PreAcquisitionEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
   * IdentityDataIdentityAcquirer constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher) {
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
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
  public function acquireIdentity(IdentityDataGroup $data_group, array $options = []) {
    $threshold = isset($options['confidence_threshold']) ? $options['confidence_threshold'] : static::ACQUISITION_CONFIDENCE_THRESHOLD;
    $inclusion_threshold = isset($options['inclusion_threshold']) ? $options['inclusion_threshold'] : static::ACQUISITION_INCLUSION_THRESHOLD;

    // Allow modules to massage data in the group
    $event = new PreAcquisitionEvent($data_group);
    $this->eventDispatcher->dispatch(IdentityEvents::PRE_ACQUISITION, $event);

    // Order datas by their acquisition priority.
    $datas = $data_group->getDatas();
    usort($datas, function(IdentityDataInterface $a, IdentityDataInterface $b) {
      return $a->acquisitionPriority() > $b->acquisitionPriority() ? -1 : 1;
    });

    $ordered_datas = $datas;
    $all_matches = [];
    while ($data = array_shift($ordered_datas)) {
      // Find all matching parties
      $data_matches = $data->findMatches();

      foreach ($data_matches as $data_match) {
        if (isset($all_matches[$data_match->getIdentity()->id()])) {
          continue;
        }

        $all_matches[$data_match->getIdentity()->id()] = $data_match;
        foreach ($datas as $so_data) {
          if ($so_data == $data) {
            continue;
          }

          $so_data->supportOrOppose($data_match);
        }
      }

      // @todo: Find a way of not going any further if the score is high enough.
    }

    /** @var \Drupal\identity\IdentityMatch $top_match */
    $top_match = NULL;
    foreach ($all_matches as $match) {
      if (empty($top_match) || $match->getScore() > $top_match->getScore()) {
        $top_match = $match;
      }
    }

    if ($top_match && ($top_match->getScore() >= $threshold)) {
      // @todo: Check if there are other > threshold matches and trigger
      // merge requests.

      return new IdentityAcquisitionResult($top_match->getIdentity(), IdentityAcquisitionResult::METHOD_FOUND, $all_matches);
    }
    else {
      /** @var \Drupal\identity\Entity\Identity $identity */
      $identity = $this->entityTypeManager->getStorage('identity')->create();
      return new IdentityAcquisitionResult($identity, IdentityAcquisitionResult::METHOD_CREATE, $all_matches);
    }
  }

}
