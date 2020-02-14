<?php

namespace Drupal\identity\Plugin\IdentityDataClass;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\identity\Entity\Identity;
use Drupal\identity\Entity\IdentityData;
use Drupal\identity\IdentityLabelContext;

trait LabelingIdentityDataClassTrait {

  /**
   * {@inheritdoc}
   */
  public function identityLabel(Identity $identity, IdentityLabelContext $context, BubbleableMetadata $bubbleable_metadata) {
    $filters = [];
    if ($type_preference = $context->getDataPreference(IdentityLabelContext::DATA_PREFERENCE_TYPE)) {
      $filters['type'] = $type_preference;
    }

    $data = $identity->getData($this->getPluginId(), $filters);
    if (empty($data)) {
      $data = $identity->getData($this->getPluginId());
    }

    if (empty($data)) {
      return NULL;
    }

    $datum = is_array($data) ? reset($data) : $data->current();
    $bubbleable_metadata->addCacheableDependency($datum);

    return $this->buildIdentityLabel($datum);
  }

  /**
   * @param \Drupal\identity\Entity\IdentityData $data
   *
   * @return string
   */
  abstract protected function buildIdentityLabel(IdentityData $data);

  /**
   * {@inheritdoc}
   */
  public function identityLabelPriority(Identity $identity, IdentityLabelContext $context, BubbleableMetadata $bubbleableMetadata) {
    if ($context->getDataPreference(IdentityLabelContext::DATA_PREFERENCE_CLASS) == $this->getPluginId()) {
      return 100;
    }

    return 10;
  }

}
