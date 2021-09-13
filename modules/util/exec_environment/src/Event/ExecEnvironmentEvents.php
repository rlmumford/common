<?php

namespace Drupal\exec_environment\Event;

/**
 * Events that are defined by the exec environment module.
 */
final class ExecEnvironmentEvents {

  /**
   * Detect the base environment.
   */
  const DETECT_DEFAULT_ENVIRONMENT = 'exec_environment.detect_default_environment';

  /**
   * Detect an entity build environment.
   *
   * Should be suffixed with the entity type.
   */
  const DETECT_ENTITY_BUILD_ENVIRONMENT = 'exec_environment.detect_entity_build_environment.';

}
