<?php

namespace Drupal\identity\Plugin\IdentityDataClass;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\identity\Entity\Identity;
use Drupal\identity\IdentityLabelContext;

interface LabelingIdentityDataClassInterface {

  /**
   * Create label for a given identity.
   *
   * @param \Drupal\identity\Entity\Identity $identity
   * @param \Drupal\identity\IdentityLabelContext $context
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleableMetadata
   *
   * @return string
   */
  public function identityLabel(Identity $identity, IdentityLabelContext $context, BubbleableMetadata $bubbleableMetadata);

  /**
   * What priority does this data class have when labelling in this context.
   *
   * @param \Drupal\identity\Entity\Identity $identity
   * @param \Drupal\identity\IdentityLabelContext $context
   *
   * @return int
   */
  public function identityLabelPriority(Identity $identity, IdentityLabelContext $context, BubbleableMetadata $bubbleableMetadata);

}
