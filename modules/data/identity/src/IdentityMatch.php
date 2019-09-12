<?php

namespace Drupal\identity;

use Drupal\identity\Entity\IdentityDataInterface;

class IdentityMatch {

  /**
   * @var \Drupal\identity\Entity\Identity
   */
  protected $identity;

  /**
   * @var \Drupal\identity\Entity\IdentityDataInterface
   */
  protected $matchData;

  /**
   * @var
   */
  protected $searchData;

  /**
   * @var int
   */
  protected $initialScore = 0;

  /**
   * @var int
   */
  protected $score = 0;

  /**
   * @var array
   *   Each item has the keys:
   *   - 'data' => \Drupal\identity\Entity\IdentityDataInterface
   *   - 'effect' => integer
   */
  protected $supportingDatas = [];

  /**
   * @var array
   *   Each item has the keys:
   *   - 'data' => \Drupal\identity\Entity\IdentityDataInterface
   *   - 'effect' => integer
   */
  protected $opposingDatas = [];

  /**
   * IdentityMatch constructor.
   *
   * @param $score
   * @param \Drupal\identity\Entity\IdentityDataInterface $match_data
   * @param \Drupal\identity\Entity\IdentityDataInterface $search_data
   */
  public function __construct($score, IdentityDataInterface $match_data, IdentityDataInterface $search_data) {
    $this->identity = $match_data->getIdentity();
    $this->matchData = $match_data;
    $this->searchData = $search_data;
    $this->initialScore = $this->score = $score;
  }

  /**
   * @return \Drupal\identity\Entity\Identity
   */
  public function getIdentity() {
    return $this->identity;
  }

  /**
   * Support a match
   *
   * @param \Drupal\identity\Entity\IdentityDataInterface $data
   * @param $score
   */
  public function supportMatch(IdentityDataInterface $data, $score) {
    $this->supportingDatas[] = [
      'data' => $data,
      'effect' => $score,
    ];

    $this->score += $score;
  }

  /**
   * Oppose a match.
   *
   * @param \Drupal\identity\Entity\IdentityDataInterface $data
   * @param $score
   */
  public function opposeMatch(IdentityDataInterface $data, $score) {
    $this->opposingDatas[] = [
      'data' => $data,
      'effect' => $score,
    ];

    $this->score -= $score;
  }

  /**
   * Get the match score.
   *
   * @return int
   */
  public function getScore() {
    return $this->score;
  }
}
