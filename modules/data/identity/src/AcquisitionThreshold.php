<?php

namespace Drupal\identity;

class AcquisitionThreshold {

  /**
   * @var \Drupal\identity\AcquisitionThresholdComponent[]
   */
  protected $components;

  /**
   * Get a new threshold
   *
   * @return \Drupal\identity\AcquisitionThreshold
   */
  public static function create() {
    return new static();
  }

  /**
   * Add a component to this threshold.
   *
   * @param \Drupal\identity\AcquisitionThresholdComponent|string $component
   *
   * @return static
   */
  public function addComponent($component, $type = NULL, $level = []) {
    if ($component instanceof AcquisitionThresholdComponent) {
      $this->components[] = $component;
    }
    else {
      $this->components[] = new AcquisitionThresholdComponent($component, $type, $level);
    }

    return $this;
  }

  /**
   * Test the threshold
   *
   * @param \Drupal\identity\IdentityMatch $match
   *
   * return bool
   */
  public function isReached(IdentityMatch $match) {
    $results = [];

    foreach ($this->components as $component) {
      $results[] = $component->test($match);
    }

    return !in_array(AcquisitionThresholdComponent::SO_FORBID, $results);
  }
}
