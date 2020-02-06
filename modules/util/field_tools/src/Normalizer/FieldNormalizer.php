<?php

namespace Drupal\field_tools\Normalizer;

use Drupal\serialization\Normalizer\FieldNormalizer as BaseFieldNormalizer;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * Class FieldNormalizer
 *
 * We override the base field normalize to allow work based on main property
 * name or not in a list.
 *
 * @package Drupal\field_tools\Normalizer
 */
class FieldNormalizer extends BaseFieldNormalizer {

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    if (!is_array($data)) {
      if (!isset($context['target_instance'])) {
        throw new InvalidArgumentException('$context[\'target_instance\'] must be set to denormalize with the FieldNormalizer');
      }

      /** @var \Drupal\Core\Field\FieldItemListInterface $items */
      $items = $context['target_instance'];
      if ($main_property_name = $items->getItemDefinition()->getMainPropertyName()) {
        $data[$main_property_name] = $data;
      }
      else {
        throw new UnexpectedValueException(sprintf('Field values for "%s" must use an array structure', $items->getName()));
      }
    }

    if (!is_numeric(key($data))) {
      $data = [$data];
    }

    return parent::denormalize($data, $class, $format, $context);
  }

}
