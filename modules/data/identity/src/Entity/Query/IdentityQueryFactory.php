<?php

namespace Drupal\identity\Entity\Query;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\Sql\QueryFactory;

class IdentityQueryFactory extends QueryFactory {

  /**
   * {@inheritdoc}
   */
  public function get(EntityTypeInterface $entity_type, $conjunction) {
    if ($entity_type->id() == 'identity') {
      return new IdentityQuery($entity_type, $conjunction, $this->connection, $this->namespaces);
    }
    if ($entity_type->id() == 'identity_data') {
      return new IdentityDataQuery($entity_type, $conjunction, $this->connection, $this->namespaces);
    }

    throw new \Exception('Invalid entity type passed');
  }
}
