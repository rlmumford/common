<?php
/**
 * Created by PhpStorm.
 * User: Rob
 * Date: 15/11/2019
 * Time: 17:43
 */

namespace Drupal\identity\Entity\Query;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\Query\Sql\Query;

class IdentityQuery extends Query {

  /**
   * @param \Drupal\Core\Database\Query\SelectInterface $sql_query
   *
   * @return \Drupal\Core\Entity\Query\Sql\TablesInterface|\Drupal\identity\Entity\Query\IdentityTables
   */
  public function getTables(SelectInterface $sql_query) {
    return new IdentityTables($sql_query);
  }
}
