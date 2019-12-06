<?php

namespace Drupal\identity\Plugin\IdentityDataClass;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\identity\Entity\IdentityData;
use Drupal\identity\Entity\IdentityDataStorage;
use Drupal\identity\Entity\IdentityStorage;
use Drupal\identity\Field\IdentityEntityReferenceItem;
use Drupal\identity\IdentityDataGroup;
use Drupal\identity\IdentityDataIdentityAcquirerInterface;
use Drupal\identity\IdentityMatch;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class RelationshipIdentityDataClassBase
 *
 * @package Drupal\identity\Plugin\IdentityDataClass
 */
class RelationshipIdentityDataClassBase extends IdentityDataClassBase {

  /**
   * @var \Drupal\identity\IdentityDataIdentityAcquirerInterface
   */
  protected $identityAcquirer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('identity'),
      $container->get('entity_type.manager')->getStorage('identity_data'),
      $container->get('identity.acquirer')
    );
  }

  /**
   * IdentityDataClassBase constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\identity\Entity\IdentityStorage $identity_storage
   * @param \Drupal\identity\Entity\IdentityDataStorage $identity_data_storage
   * @param \Drupal\identity\IdentityDataIdentityAcquirerInterface $identity_acquirer;
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    IdentityStorage $identity_storage,
    IdentityDataStorage $identity_data_storage,
    IdentityDataIdentityAcquirerInterface $identity_acquirer
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $identity_storage, $identity_data_storage);

    $this->identityAcquirer = $identity_acquirer;
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = [];

    $fields['other_identity'] = BaseFieldDefinition::create('entity_reference')
      ->setSetting('target_type', 'identity')
      ->setLabel(new TranslatableMarkup('Other Identity'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['other_identity']->setItemDefinition(
      $fields['other_identity']
        ->getItemDefinition()
        ->setClass(IdentityEntityReferenceItem::class)
    );

    return $fields;
  }

  /**
   * @param $type
   * @param $reference
   * @param null $value
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\identity\Entity\IdentityData
   */
  public function createData($type, $reference, $value = NULL) {
    $data = parent::createData($type, $reference);

    if (isset($value['other_identity'])) {
      $this->initOtherIdentity($data, $value['other_identity']);

      $metadata = $value;
      unset($metadata['other_identity']);
    }
    else {
      $this->initOtherIdentity($data, $value);
      $metadata = !empty($value['metadata']) ? $value['metadata'] : [];
    }

    $this->initMetadata($data, $metadata);

    return $data;
  }

  /**
   * Support or Oppose
   *
   * @param \Drupal\identity\Entity\IdentityData $data
   * @param \Drupal\identity\IdentityMatch $match
   */
  public function supportOrOppose(IdentityData $data, IdentityMatch $match) {
    $identity = $match->getIdentity();

    foreach ($identity->getData($this->pluginId) as $identity_data) {
      if ($data->other_identity->target_id == $identity_data->other_identity->target_id) {
        $match->supportMatch($identity_data, $this->getSupportScore($data, $identity_data));
      }
    }
  }

  /**
   * @param $data
   * @param $other_identity
   *
   * @return int
   */
  protected function getSupportScore($data, $other_identity) {
    return 10;
  }

  /**
   * Correctly place any relationship metadata on the data.
   *
   * @param $data
   * @param $metadata
   */
  protected function initMetadata($data, $metadata) {}

  /**
   * Run an acquisition on the other identity.
   *
   * @param $data
   * @param $other_identity
   */
  protected function initOtherIdentity($data, $other_identity) {
    if (
      ($other_identity instanceof IdentityDataGroup) ||
      (is_array($other_identity) && !isset($other_identity['target_id']) && !isset($other_identity['target_uuid']))
    ) {
      $group = $other_identity;
      if (!($group instanceof IdentityDataGroup)) {
        $group = new IdentityDataGroup($group);
      }

      $data->__acquisitionResult = $result = $this->identityAcquirer->acquireIdentity($group);
      $data->other_identity = $result->getIdentity();
    }
    else if (is_array($other_identity)) {
      if (!isset($other_identity['target_id']) && !empty($other_identity['target_uuid'])) {
        $query = $this->identityStorage->getQuery();
        $query->condition('uuid', $other_identity['target_uuid']);
        $ids = $query->execute();

        if ($ids) {
          $other_identity['target_id'] = reset($ids);
        }
      }

      if (isset($other_identity['target_id'])) {
        $data->other_identity = $other_identity;
      }
    }
    else if (is_numeric($other_identity)) {
      $data->other_identity = $other_identity;
    }
    else if (is_string($other_identity)) {
      $query = $this->identityStorage->getQuery();
      $query->condition('uuid', $other_identity);
      $query->range(0, 1);
      $ids = $query->execute();

      if (count($ids)) {
        $data->other_identity = reset($ids);
      }
    }
  }

}
