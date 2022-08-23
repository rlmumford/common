<?php

namespace Drupal\identity;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\identity\Entity\Identity;

interface IdentityLabelerInterface {

  /**
   * Label the entity.
   *
   * @param \Drupal\identity\Entity\Identity $identity
   *   The identity to get a label for.
   * @param \Drupal\identity\IdentityLabelContext|NULL $context
   *   The label context.
   * @param \Drupal\Core\Render\BubbleableMetadata|null $bubbleable_metadata
   *   The cache metadata.
   *
   * @return string|null
   *   A label for the provided identity.
   */
  public function label(Identity $identity, IdentityLabelContext $context = NULL, BubbleableMetadata $bubbleable_metadata = NULL) : ?string;

  /**
   * Label a set of entities.
   *
   * @param \Drupal\identity\Entity\Identity[] $identities
   *   And array of identities to be labeled.
   * @param \Drupal\identity\IdentityLabelContext|NULL $context
   *   The label context.
   * @param \Drupal\Core\Render\BubbleableMetadata|NULL $bubbleable_metadata
   *   Cache metadata.
   *
   * @return array
   *   An array of labels keyed by the keys of $identities.
   */
  public function labelMultiple(array $identities, IdentityLabelContext $context = NULL, BubbleableMetadata $bubbleable_metadata = NULL) : array;
}
