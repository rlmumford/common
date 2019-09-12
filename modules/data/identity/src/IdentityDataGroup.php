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
   * @return \Drupal\identity\Entity\IdentityData[]
   */
  public function getDatas() {
    return $this->datas;
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


}
