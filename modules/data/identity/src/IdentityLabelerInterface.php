<?php

namespace Drupal\identity;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\identity\Entity\Identity;

interface IdentityLabelerInterface {

  /**
   * Label the entity.
   *
   * @param \Drupal\identity\Entity\Identity $identity
   * @param \Drupal\identity\IdentityLabelContext|NULL $context
   * @param \Drupal\Core\Render\BubbleableMetadata|NULL $bubbleable_metadata
   *
   * @return mixed
   */
  public function label(Identity $identity, IdentityLabelContext $context = NULL, BubbleableMetadata $bubbleable_metadata = NULL);
}
