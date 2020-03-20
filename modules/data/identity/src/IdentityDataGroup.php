<?php

namespace Drupal\identity;

use Drupal\identity\Entity\IdentityDataSource;

/**
 * Class IdentityDataGroup
 *
 * Represents a group of Identities. It is assumed that data in the same group
 * all belong to the same identity.
 *
 * @package Drupal\identity
 */
class IdentityDataGroup {

  /**
   * The datas in this group.
   *
   * @var \Drupal\identity\Entity\IdentityData[]
   */
  protected $datas = [];

  /**
   * The source of datas in this group.
   *
   * @var \Drupal\identity\Entity\IdentityDataSource
   */
  protected $source;

  /**
   * The ID of this group.
   *
   * @var string
   */
  protected $id;

  /**
   * IdentityDataGroup constructor.
   */
  public function __construct(array $datas, IdentityDataSource $source = NULL, $id = NULL) {
    $this->datas = $datas;
    $this->source = $source;
    $this->id = !empty($id) ? $id : \Drupal::service('uuid')->generate();
  }

  /**
   * Get the datas from this group. Filter by class.
   *
   * @param string $class
   *
   * @return \Drupal\identity\Entity\IdentityData[]
   */
  public function getDatas($class = FALSE) {
    if (empty($class)) {
      return $this->datas;
    }

    $class_datas = [];
    foreach ($this->datas as $data) {
      if ($data->bundle() === $class) {
        $class_datas[] = $data;
      }
    }

    return $class_datas;
  }

  /**
   * @return string
   */
  public function getId() {
    return $this->id;
  }

  /**
   * @return \Drupal\identity\Entity\IdentityDataSource
   */
  public function getSource() {
    return $this->source;
  }

  /**
   * @param \Drupal\identity\Entity\IdentityDataSource $source
   *
   * return static
   */
  public function setSource(IdentityDataSource $source) {
    $this->source = $source;
    return $this;
  }
}
