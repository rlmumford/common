<?php

namespace Drupal\exec_environment\Event;

use Drupal\exec_environment\Environment;
use Symfony\Component\EventDispatcher\Event;

/**
 * Object for environment detection events.
 */
class EnvironmentDetectionEvent extends Event {

  /**
   * The environment being detected.
   *
   * @var \Drupal\exec_environment\Environment
   */
  protected $environment;

  /**
   * Whether or not an environment has been applied from this event.
   *
   * @var bool
   */
  protected $applied = FALSE;

  /**
   * Get the detected environment.
   *
   * @return \Drupal\exec_environment\Environment|null
   *   The detected environment. If no environment was detected and the event
   *   has already been applied, return NULL.
   */
  public function getEnvironment() :? Environment {
    if (!$this->environment && !$this->applied) {
      $this->environment = new Environment();
    }

    return $this->environment;
  }

  /**
   * Apply the environment if one was ever generated.
   */
  public function applyEnvironment() {
    if ($this->environment) {
      \Drupal::service('environment_stack')->applyEnvironment($this->environment);
    }
    $this->applied = TRUE;
  }

  /**
   * Reset the environment if one was applies.
   */
  public function resetEnvironment() {
    if ($this->environment) {
      \Drupal::service('environment_stack')->resetEnvironment();
    }
  }

}
