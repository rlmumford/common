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
