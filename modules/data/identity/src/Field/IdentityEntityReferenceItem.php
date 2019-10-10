<?php

namespace Drupal\identity\Field;

use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\identity\IdentityDataGroup;

/**
 * Class IdentityEntityReferenceItem
 *
 * Extend the en
 *
 * @package Drupal\identity\Field
 */
class IdentityEntityReferenceItem extends EntityReferenceItem {

  /**
   * The identity data
   *
   * @var \Drupal\identity\Entity\IdentityData[]
   */
  protected $data = [];

  /**
   * @var \Drupal\identity\Entity\IdentityDataSource
   */
  protected $source = NULL;

  /**
   * Acquire an identity to store
   */
  public function acquireIdentity() {
    $group = new IdentityDataGroup($this->data, $this->source);

    /** @var \Drupal\identity\IdentityDataIdentityAcquirerInterface $acquirer */
    $acquirer = \Drupal::service('identity.acquirer');
    $result = $acquirer->acquireIdentity($group);

    $this->set('entity', $result->getIdentity());

    return $result;
  }

  /**
   * Set the data.
   *
   * @param \Drupal\identity\Entity\IdentityData[] $data
   */
  public function setData(array $data =[]) {
    $this->data = $data;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    if (!$this->entity && !$this->target_id && !empty($this->data)) {
      $this->acquireIdentity();
    }

    parent::preSave();
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return parent::isEmpty() && empty($this->data);
  }
}
