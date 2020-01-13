<?php

namespace Drupal\identity\Entity\Query;

use Drupal\Core\Entity\Query\QueryInterface;

interface IdentityDataQueryInterface extends QueryInterface {

  /**
   * Get this query to return one row per identity.
   *
   * @param string $grouping_method
   *   How to select the identity_data id when forcing one result per identity.
   *
   * @return static
   */
  public function identityDistinct($grouping_method = 'MAX');

}
