<?php

namespace Drupal\identity;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\identity\Entity\IdentityDataInterface;

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
   * IdentityDataIdentityAcquirer constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
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
    $threshold = 100;

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

    // Sort the matches by the match score.
    uasort($all_matches, function (IdentityMatch $match_a, IdentityMatch $match_b) {
      return $match_a->getScore() > $match_b->getScore() ? -1 : 1;
    });

    $top_match = array_shift($all_matches);
    if ($top_match && $top_match->getScore() > $threshold) {
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
