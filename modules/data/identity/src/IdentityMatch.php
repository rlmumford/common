<?php

namespace Drupal\identity;

use Drupal\identity\Entity\IdentityDataInterface;
use Drupal\identity\Exception\IllegalMatchDataException;

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
   * @param \Drupal\identity\Entity\IdentityDataInterface $search_data
   * @param \Drupal\identity\Entity\IdentityDataInterface $match_data
   * @param $score
   * @param array $support_level
   */
  public function __construct(IdentityDataInterface $search_data, IdentityDataInterface $match_data, $score, $support_level = []) {
    $this->matchData = $match_data;
    $this->searchData = $search_data;
    $this->initialScore = $this->score = $score;

    $this->supportingDatas[$search_data->uuid()] = [
      'search_data' => $search_data,
      'match_data' => [
        $match_data->uuid() => $match_data,
      ],
      'effect' => $score,
      'level' => $support_level,
    ];
  }

  /**
   * @return \Drupal\identity\Entity\Identity
   */
  public function getIdentity() {
    if (!$this->identity) {
      $this->identity = $this->matchData->getIdentity();
    }

    return $this->identity;
  }

  /**
   * @return int
   */
  public function getIdentityId() {
    return $this->matchData->getIdentityId();
  }

  /**
   * Support a match
   *
   * @param \Drupal\identity\Entity\IdentityDataInterface $search_data
   * @param \Drupal\identity\Entity\IdentityDataInterface $match_data
   * @param $score
   * @param $support_level
   *   A string describing the support level. This is used in is sufficient.
   *
   * @return bool
   *
   * @throws \Drupal\identity\Exception\IllegalMatchDataException
   */
  public function supportMatch(IdentityDataInterface $search_data, IdentityDataInterface $match_data, $score, $support_level = []) {
    return $this->registerSupportOrOpposition('support', $search_data, $match_data, $score, $support_level);
  }

  /**
   * Oppose a match.
   *
   * @param \Drupal\identity\Entity\IdentityDataInterface $search_data
   * @param \Drupal\identity\Entity\IdentityDataInterface $match_data
   * @param $score
   * @param array $oppositon_level
   *
   * @return bool
   *
   * @throws \Drupal\identity\Exception\IllegalMatchDataException
   */
  public function opposeMatch(IdentityDataInterface $search_data, IdentityDataInterface $match_data, $score, $oppositon_level = []) {
    return $this->registerSupportOrOpposition('oppose', $search_data, $match_data, $score, $oppositon_level);
  }

  /**
   * Register support or opposition to a match.   *
   *
   * @param $so
   * @param \Drupal\identity\Entity\IdentityDataInterface $search_data
   * @param \Drupal\identity\Entity\IdentityDataInterface $match_data
   * @param $score
   * @param array $level
   *
   * @throws \Drupal\identity\Exception\IllegalMatchDataException
   *
   * @return bool
   *   True if this search data has exhausted its matching potential. False otherwise.
   */
  protected function registerSupportOrOpposition($so, IdentityDataInterface $search_data, IdentityDataInterface $match_data, $score, $level = []) {
    if ($match_data->getIdentityId() !== $this->matchData->getIdentityId()) {
      throw new IllegalMatchDataException('Provided match data does not reference the same identity as identity match.');
    }

    $var = $so == 'support' ? 'supportingDatas' : 'opposingDatas';

    // Each "search_data" is limited in how far it can support a match - i.e.
    // each level once
    if (!isset($this->{$var}[$search_data->uuid()])) {
      $this->{$var}[$search_data->uuid()] = [
        'search_data' => $search_data,
        'match_data' => [
          $match_data->uuid() => $match_data,
        ],
        'effect' => $score,
        'level' => is_array($level) ? $level : [$level],
      ];
    }
    else {
      $this->{$var}[$search_data->uuid()]['match_data'][$match_data->uuid()] = $match_data;

      $level = is_array($level) ? $level : [$level];
      if (!empty($level) && array_diff($level, $this->{$var}[$search_data->uuid()]['level'])) {
        $this->{$var}[$search_data->uuid()]['level'] = array_unique(array_merge($level, $this->{$var}[$search_data->uuid()]['level']));
        $this->{$var}[$search_data->uuid()]['effect'] = max($this->{$var}[$search_data->uuid()]['effect'], $score);
      }
    }

    $this->recomputeScore();

    $possible_levels = $so == 'support' ? $search_data->possibleMatchSupportLevels() : $search_data->possibleMatchOppositionLevels();

    return empty($possible_levels) || (count(array_intersect($possible_levels, $this->{$var}[$search_data->uuid()]['level'])) === count($possible_levels));
  }

  /**
   * Recompute the score.
   */
  protected function recomputeScore() {
    $this->score = 0;

    foreach ($this->supportingDatas as $supportingData) {
      $this->score += $supportingData['effect'];
    }

    foreach ($this->opposingDatas as $opposingData) {
      $this->score -= $opposingData['effect'];
    }
  }

  /**
   * Get the match score.
   *
   * @return int
   */
  public function getScore() {
    return $this->score;
  }

  /**
   * Get the supporting datas.
   *
   * @return array
   *   Each item has the following keys:
   *   - data
   *   - score
   *   - level
   */
  public function getSupportingDatas() {
    return $this->supportingDatas;
  }

  /**
   * Get the supporting datas.
   *
   * @return array
   *   Each item has the following keys:
   *   - data
   *   - score
   *   - level
   */
  public function getOpposingDatas() {
    return $this->opposingDatas;
  }

  /**
   * Return whether the match is sufficient.
   *
   * This is calculated by collecting the threshold and comparing the data
   * available.
   */
  public function isSufficient() {
    /** @var \Drupal\identity\AcquisitionThreshold[] $acquisition_thresholds */
    $acquisition_thresholds = [
      AcquisitionThreshold::create()->addComponent('third_party_id', 'ck'),
      AcquisitionThreshold::create()->addComponent('third_party_id', 'ssn','full'),
      AcquisitionThreshold::create()->addComponent('third_party_id', 'att'),
      AcquisitionThreshold::create()
        ->addComponent('personal_name', NULL, ['given', 'family', 'suffix'])
        ->addComponent('address', NULL, ['postal_code'])
        ->addComponent('third_party_id', 'ssn', ['last4']),
      AcquisitionThreshold::create()
        ->addComponent('personal_name', NULL, ['given', 'family', 'suffix'])
        ->addComponent('address', NULL, ['postal_code', 'street']),
      AcquisitionThreshold::create()
        ->addComponent('personal_name', NULL, ['given', 'family', 'suffix'])
        ->addComponent('telephone_number'),
      AcquisitionThreshold::create()
        ->addComponent('personal_name', NULL, ['given', 'family', 'suffix'])
        ->addComponent('email_address'),
      AcquisitionThreshold::create()
        ->addComponent('personal_name', NULL, ['full'])
        ->addComponent('address', NULL, ['postal_code'])
        ->addComponent('third_party_id', 'ssn', ['last4']),
      AcquisitionThreshold::create()
        ->addComponent('personal_name', NULL, ['full'])
        ->addComponent('address', NULL, ['postal_code', 'street']),
      AcquisitionThreshold::create()
        ->addComponent('personal_name', NULL, ['full'])
        ->addComponent('telephone_number'),
      AcquisitionThreshold::create()
        ->addComponent('personal_name', NULL, ['full'])
        ->addComponent('email_address'),
      AcquisitionThreshold::create()
        ->addComponent('telephone_number')
        ->addComponent('third_party_id', 'ssn', 'last4'),
      AcquisitionThreshold::create()
        ->addComponent('email_address')
        ->addComponent('third_party_id', 'ssn', 'last4'),
      AcquisitionThreshold::create()
        ->addComponent('organization_name')
        ->addComponent('address', NULL, ['postal_code', 'street']),
      AcquisitionThreshold::create()
        ->addComponent('organization_name')
        ->addComponent('telephone_number'),
      AcquisitionThreshold::create()
        ->addComponent('organization_name')
        ->addComponent('email_address'),
      AcquisitionThreshold::create()
        ->addComponent('organization_name')
        ->addComponent('third_party_id', 'tax','full'),
    ];

    // Handle the threshold components
    foreach ($acquisition_thresholds as $threshold) {
      if ($threshold->isReached($this)) {
        return TRUE;
      }
    }

    return FALSE;
  }
}
