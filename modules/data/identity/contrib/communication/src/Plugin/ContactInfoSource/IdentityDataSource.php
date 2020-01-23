<?php

namespace Drupal\identity_communication\Plugin\ContactInfoSource;

use Drupal\communication\Contact\ContactInfo;
use Drupal\communication\Contact\ContactInfoDefinitionInterface;
use Drupal\communication\Plugin\ContactInfoSource\ContactInfoSourceBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\identity\IdentityDataClassManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IdentityDataSource extends ContactInfoSourceBase {

  /**
   * @var \Drupal\identity\IdentityDataClassManager
   */
  protected $dataClassManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.identity_data_class')
    );
  }

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, IdentityDataClassManager $data_class_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);

    $this->dataClassManager = $data_class_manager;
  }

  /**
   * Map a contact data type to identity data class.
   *
   * @param $type
   *   The contact data type.
   *
   * @return string
   *   The identity data class.
   */
  protected function mapDataType($type) {
    $map = [
      'telephone' => 'telephone_number',
    ];
    return isset($map[$type]) ? $map[$type] : $type;
  }

  /**
   * Get the relevant data field.
   *
   * @param $type
   *   The contact data type.
   *
   * @return string
   *   The field name identity data class
   */
  protected function relevantDataField($type) {
    $map = [
      'address' => 'address',
      'telephone' => 'telephone_number',
      'email' => 'email_address',
    ];

    return isset($map[$type]) ? $map[$type] : NULL;
  }

  /**
   * Collect contact info for the entity that matches.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param \Drupal\communication\Contact\ContactInfoDefinitionInterface $definition
   * @param array $options
   *
   * @return \Drupal\communication\Contact\ContactInfoInterface[]
   */
  public function collectInfo(EntityInterface $entity, ContactInfoDefinitionInterface $definition, array $options = []) {
    $info = [];

    /** @var \Drupal\identity\Entity\Identity $entity */
    $datas = $entity->getData($this->mapDataType($definition->getDataType()));
    $delta = 0;
    foreach ($datas as $data) {
      $sub_key = 'data.'.$data->bundle().'.'.$delta;
      $info[$sub_key] = new ContactInfo($definition, $entity, $this->getPluginId(), $sub_key);

      $delta++;
    }

    $sub_key = 'data.'.$data->bundle().'.NEW';
    $info[$sub_key] = new ContactInfo($definition, $entity, $this->getPluginId(), $sub_key);
    $info[$sub_key]->setIncomplete();

    return $info;
  }

  /**
   * @return mixed
   */
  public function getInfoValue(EntityInterface $entity, $key, $name, DataDefinitionInterface $definition) {
    /** @var \Drupal\identity\Entity\Identity $entity */
    list(,$class,$delta) = explode('.', $key, 3);

    if ($delta == 'NEW') {
      if ($name == "name") {
        return $entity->label();
      }
      return NULL;
    }

    $datas = $entity->getData($class);
    if (count($datas) <= $delta) {
      if ($name == "name") {
        return $entity->label();
      }

      return NULL;
    }

    $data = current(array_slice($datas, $delta, 1));
    $field_name = $this->relevantDataField($name);

    $item = $data->get($field_name)->get(0);
    if (!$item || $item->isEmpty()) {
      return NULL;
    }

    if ($name == 'email' || $name == 'telephone') {
      return $item->value;
    }
    else if ($name == 'address') {
      return $item->toArray();
    }
    else if ($name == 'name') {
      return $entity->label();
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsWriteBackInfoValue(EntityInterface $entity, $key, $name, DataDefinitionInterface $definition) {
    return ($name != "name");
  }

  /**
   * {@inheritdoc}
   */
  public function writeBackInfoValues(EntityInterface $entity, $key, DataDefinitionInterface $definition, $values) {
    /** @var \Drupal\identity\Entity\Identity $entity */
    list(,$class,$delta) = explode('.', $key, 3);

    $datas = $entity->getData($class);
    $data_storage = $this->entityTypeManager->getStorage('identity_data');
    if ($delta === 'NEW' || ($delta > count($datas))) {
      $data = $data_storage->create([
        'identity' => $entity,
        'class' => $class,
      ]);

      if ($class == 'telephone_number') {
        $data->telephone_number = $values['telephone'];
      }
      elseif ($class == 'email') {
        $data->email_address = $values['email'];
      }
      elseif ($class == 'address') {
        $data->address = $values['address'];
      }
      $data->save();

      return "data.{$class}.".count($datas);
    }
    else {
      $data = current(array_slice($datas, $delta, 1));
      if ($class == 'telephone_number') {
        $data->telephone_number = $values['telephone'];
      }
      elseif ($class == 'email') {
        $data->email_address = $values['email'];
      }
      elseif ($class == 'address') {
        $data->address = $values['address'];
      }
      $data->save();
    }

    return $key;
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param $key
   *
   * @return string
   */
  public function getLabel(EntityInterface $entity, $key) {
    /** @var \Drupal\identity\Entity\Identity $entity */
    list(,$class,$delta) = explode('.', $key, 3);

    return $this->dataClassManager->getDefinition($class)['label'];
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param $key
   *
   * @return mixed
   */
  public function getSummary(EntityInterface $entity, $key) {
    /** @var \Drupal\identity\Entity\Identity $entity */
    list(,$class,$delta) = explode('.', $key, 3);
    // TODO: Implement getSummary() method.
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param $key
   *
   * @return \Drupal\communication\Contact\ContactInfoDefinitionInterface
   */
  public function getInfoDefinition(EntityInterface $entity, $key) {
    // TODO: Implement getInfoDefinition() method.
  }
}
