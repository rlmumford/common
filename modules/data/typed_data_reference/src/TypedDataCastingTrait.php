<?php


namespace Drupal\typed_data_reference;


use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;

trait TypedDataCastingTrait {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The typed data manager service.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * Downcast a typed data object to a raw value.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $typed_data
   *   The typed data to downcast.
   *
   * @return mixed
   *   The downcasted value.
   */
  protected function downcastData(TypedDataInterface $typed_data) {
    if ($typed_data instanceof EntityAdapter) {
      return $typed_data->getEntity()->id();
    }

    return $typed_data->getValue();
  }

  /**
   * Upcast a value to a typed data object.
   *
   * @param mixed $value
   *   The value to upcast.
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $definition
   *   The definition of the data to upcast to.
   * @param \Drupal\Core\TypedData\TypedDataInterface|null $target_data
   *   The target data to set, if not provided a new piece of data will be
   *   created.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   A piece of typed data with the value set.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  protected function upcastValue($value, DataDefinitionInterface $definition, TypedDataInterface $target_data = NULL) {
    if ($definition instanceof EntityDataDefinitionInterface && !empty($value)) {
      $value = $this->entityTypeManager()->getStorage($definition->getEntityTypeId())
        ->load($value);
    }

    if (!$target_data) {
      $target_data = $this->typedDataManager()->create($definition);
    }

    $target_data->setValue($value);
    return $target_data;
  }

  /**
   * Get the typed data manager service.
   *
   * @return \Drupal\Core\TypedData\TypedDataManagerInterface
   *   The typed data manager service.
   */
  protected function typedDataManager() {
    if (!$this->typedDataManager) {
      $this->typedDataManager = \Drupal::typedDataManager();
    }

    return $this->typedDataManager;
  }

  /**
   * Get the entity type manager. service.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The typed data manager service.
   */
  protected function entityTypeManager() {
    if (!$this->entityTypeManager) {
      $this->entityTypeManager = \Drupal::entityTypeManager();
    }

    return $this->entityTypeManager;
  }

}
