<?php

namespace Drupal\identity\Plugin\IdentityDataClass;

use Drupal\entity\BundlePlugin\BundlePluginInterface;
use Drupal\identity\Entity\IdentityData;
use Drupal\identity\Entity\IdentityDataInterface;
use Drupal\identity\IdentityMatch;

interface IdentityDataClassInterface extends BundlePluginInterface {

  /**
   * Get the identity data label.
   *
   * @param \Drupal\identity\Entity\IdentityData $data
   *
   * @return string
   */
  public function dataLabel(IdentityData $data);

  /**
   * @param \Drupal\identity\Entity\IdentityData $data
   *
   * @return integer
   */
  public function acquisitionPriority(IdentityData $data);

  /**
   * @param \Drupal\identity\Entity\IdentityData $data
   *
   * @return mixed
   */
  public function findMatches(IdentityData $data);

  /**
   * Work out whether the data supports or opposes
   *
   * @param \Drupal\identity\Entity\IdentityData $data
   *   The data that has been supplied to the acquisition function.
   * @param \Drupal\identity\IdentityMatch $match
   *   The match that has been found by find matches.
   */
  public function supportOrOppose(IdentityData $data, IdentityMatch $match);

  /**
   * The possible match support levels for this search data.
   *
   * @param \Drupal\identity\Entity\IdentityDataInterface $search_data
   *
   * @return string[]
   */
  public function possibleMatchSupportLevels(IdentityDataInterface $search_data);

  /**
   * The possible match opposition levels for this search data.
   *
   * @param \Drupal\identity\Entity\IdentityDataInterface $search_data
   *
   * @return string[]
   */
  public function possibleMatchOppositionLevels(IdentityDataInterface $search_data);

  /**
   * Get the type options.
   *
   * @return array
   *   Type options for this data class.
   */
  public function typeOptions();

  /**
   * Create a piece of data of this class.
   *
   * @param string $type
   * @param string $reference
   * @param mixed $value
   *
   * @return IdentityData
   */
  public function createData($type, $reference, $value = NULL);
}
