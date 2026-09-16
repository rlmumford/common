<?php

namespace Drupal\typed_data_plus\Plugin\Context;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\ListDataDefinitionInterface;

/**
 * Preserves complex property definitions when exposing selector suggestions.
 */
class DataContextDefinition extends ContextDefinition {

  /**
   * The original data definition, including its complex properties.
   */
  protected DataDefinitionInterface $dataDefinition;

  /**
   * Wraps a data definition for Drupal's context matching APIs.
   */
  public static function fromDataDefinition(DataDefinitionInterface $definition): ContextDefinitionInterface {
    $multiple = $definition instanceof ListDataDefinitionInterface;
    $item = $multiple ? $definition->getItemDefinition() : $definition;
    $type = $item->getDataType();
    if (str_starts_with($type, 'entity:')) {
      $context = ContextDefinition::create($type);
    }
    else {
      $context = new static($type);
      $context->dataDefinition = $definition;
    }
    return $context->setLabel($definition->getLabel())
      ->setRequired($definition->isRequired())
      ->setMultiple($multiple)
      ->setConstraints($item->getConstraints());
  }

  /**
   * {@inheritdoc}
   */
  public function getDataDefinition() {
    return $this->dataDefinition;
  }

}
