<?php

namespace Drupal\identity\Event;

final class IdentityEvents {

  /**
   * Name of event filed just after merging two identities.
   *
   * @Event
   */
  const POST_MERGE = 'identity.post_merge';

  /**
   * Name of event filed just before acquisition process.
   *
   * @Event
   */
  const PRE_ACQUISITION = 'identity.pre_acquisition';
}
