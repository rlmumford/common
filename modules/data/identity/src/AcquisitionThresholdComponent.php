<?php

namespace Drupal\identity;

class AcquisitionThresholdComponent {

  const SO_SUPPORT = 'support';
  const SO_FORBID = 'forbid';

  /**
   * The supporting/opposing identity data class.
   *
   * @var string
   */
  protected $class;

  /**
   * The supporting/opposing identity data type
   *
   * @var string
   */
  protected $type;

  /**
   * The required level
   *
   * @var array|string
   */
  protected $required_level;

  /**
   * Whether this component will support or forbid the match
   *
   * @var string
   */
  protected $s_or_f;

  /**
   * AcquisitionThresholdComponent constructor.
   *
   * @param string $class
   * @param string $type
   * @param array|string $required_level
   * @param string $s_or_f
   */
  public function __construct($class, $type = NULL, $required_level = [], $s_or_f = self::SO_SUPPORT) {
    $this->class = $class;
    $this->type = $type;
    $this->required_level = $required_level;
    $this->s_or_f = $s_or_f;
  }

  /**
   * Test this threshold component
   *
   * @param \Drupal\identity\IdentityMatch $match
   */
  public function test(IdentityMatch $match) {
    $relevant_datas = $this->s_or_f == static::SO_SUPPORT ? $match->getSupportingDatas() : $match->getOpposingDatas();

    foreach ($relevant_datas as $relevant_data) {
      /** @var \Drupal\identity\Entity\IdentityData $id_data */
      $id_data = $relevant_data['search_data'];
      if ($id_data->bundle() === $this->class) {
        if (!$this->type || ($this->type == $id_data->type->value)) {
          if (empty($this->required_level)) {
            return $this->s_or_f;
          }

          $data_level = is_array($relevant_data['level']) ? $relevant_data['level'] : [$relevant_data['level']];
          $required_level = is_array($this->required_level) ? $this->required_level : [$this->required_level];

          $overlap = array_intersect($required_level, $data_level);
          if (count($required_level) === count($overlap)) {
            return $this->s_or_f;
          }
        }
      }
    }

    return ($this->s_or_f == static::SO_SUPPORT) ? static::SO_FORBID : static::SO_SUPPORT;
  }

}
