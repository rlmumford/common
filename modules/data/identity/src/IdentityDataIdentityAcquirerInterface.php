<?php

namespace Drupal\identity;

interface IdentityDataIdentityAcquirerInterface {

  /**
   * The threshold fo acquisition confidence.
   *
   * Above this number the identity will be used, below it a new identity will
   * be created.
   */
  const ACQUISITION_CONFIDENCE_THRESHOLD = 100;

  /**
   * The threshold to include a match in results at all.
   */
  const ACQUISITION_INCLUSION_THRESHOLD = 10;

  /**
   * Acquire an identity for the
   *
   * @param \Drupal\identity\IdentityDataGroup $data_group
   * @param array $options
   *   Options governing this acquisition process.
   *
   * @return \Drupal\identity\IdentityAcquisitionResult
   */
  public function acquireIdentity(IdentityDataGroup $data_group, array $options = []);
}
