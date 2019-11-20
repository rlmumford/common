<?php

namespace Drupal\identity\Entity\Query;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\Sql\QueryFactory;

class IdentityQueryFactory extends QueryFactory {

  /**
   * {@inheritdoc}
   */
  public function get(EntityTypeInterface $entity_type, $conjunction) {
    return new IdentityQuery($entity_type, $conjunction, $this->connection, $this->namespaces);
  }
}
