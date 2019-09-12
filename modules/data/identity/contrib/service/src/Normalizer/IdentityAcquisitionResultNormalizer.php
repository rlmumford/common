<?php

namespace Drupal\identity_service\Normalizer;

use Drupal\identity\IdentityAcquisitionResult;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Class IdentityAcquisitionResultNormalizer
 *
 * @package Drupal\identity_service\Normalizer
 */
class IdentityAcquisitionResultNormalizer extends NormalizerBase {

  protected $supportedInterfaceOrClass = IdentityAcquisitionResult::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    /** @var \Drupal\identity\IdentityAcquisitionResult $object */
    $attributes = [];

    $attributes['identity'] = $this->serializer->normalize($object->getIdentity(), $format, $context);
    $attributes['method'] = $object->getMethod();

    return $attributes;
  }
}
