<?php

namespace Drupal\typed_data_reference;

use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\typed_data\Context\ContextDefinition;

/**
 * Trait to help converting from data definitions to context definitions.
 */
trait TypedDataDefinitionToContextDefinitionTrait {

  /**
   * Convert a data definition into a context definition.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $data_definition
   *   The data definition.
   *
   * @return \Drupal\Core\Plugin\Context\ContextDefinitionInterface
   *   The context definition.
   */
  protected function contextDefinitionForDataDefinition(DataDefinitionInterface $data_definition) : ContextDefinitionInterface {
    if ($data_definition->isList()) {
      /** @var \Drupal\Core\TypedData\ListDataDefinitionInterface $list_definition */
      $list_definition = $data_definition;
      $data_definition = $list_definition->getItemDefinition();
    }

    $class = strpos($data_definition->getDataType(), 'entity:') === 0 ?
      EntityContextDefinition::class : ContextDefinition::class;

    // EntityContextDefinition does not support data types of the form
    // entity:{entity_type}:{bundle}.
    $data_type = $data_definition->getDataType();
    $data_constraints = $data_definition->getConstraints();
    if ($class == EntityContextDefinition::class) {
      [,$entity_type_id, $bundle] = explode(':', $data_type . ":");
      $data_type = "entity:{$entity_type_id}";
      if ($bundle) {
        $data_constraints["Bundle"] = $bundle;
      }
    }

    $context_definition = $class::create($data_type)
      ->setMultiple(isset($list_definition))
      ->setLabel(isset($list_definition) ? $list_definition->getLabel() : $data_definition->getLabel())
      ->setDescription(isset($list_definition) ? $list_definition->getDescription() : $data_definition->getDescription())
      ->setRequired($data_definition->isRequired());

    $constraints = $context_definition->getConstraints();
    $context_definition->setConstraints($constraints + $data_constraints);

    return $context_definition;
  }

}
