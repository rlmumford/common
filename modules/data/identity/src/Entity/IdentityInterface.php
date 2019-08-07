<?php

namespace Drupal\identity\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

interface IdentityInterface extends ContentEntityInterface {

  /**
   * Get the datas of a certain type.
   *
   * @param $type
   * @param array $filters
   *
   * @return \Drupal\identity\Entity\IdentityDataInterface[]
   */
  public function getData($type, array $filters = []);

}
