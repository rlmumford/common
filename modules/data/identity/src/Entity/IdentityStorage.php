<?php

namespace Drupal\identity\Entity;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

class IdentityStorage extends SqlContentEntityStorage {

  /**
   * {@inheritdoc}
   */
  protected function getQueryServiceName() {
    return 'identity.query.sql';
  }
}
