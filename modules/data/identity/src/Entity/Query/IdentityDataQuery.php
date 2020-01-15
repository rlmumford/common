<?php

namespace Drupal\identity\Entity\Query;

use Drupal\Core\Entity\Query\Sql\Query;

class IdentityDataQuery extends Query implements IdentityDataQueryInterface {

  /**
   * Whether we're forcing one row per identity.
   *
   * @var bool
   */
  protected $forceIdentityDistinct = FALSE;

  /**
   * How to select the identity_data id when doing one per identity.
   *
   * @var string
   */
  protected $forceIdentityDistinctGroupingMethod = 'MAX';

  /**
   * Store whether we need to split the id and vid from the result.
   *
   * @var bool
   */
  protected $queryNeedsIdVidSplit = FALSE;

  /**
   * {@inheritdoc}
   */
  public function identityDistinct($grouping_method = 'MAX') {
    $this->forceIdentityDistinct = TRUE;
    $this->forceIdentityDistinctGroupingMethod = $grouping_method;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepare() {
    parent::prepare();

    if ($this->forceIdentityDistinct) {
      $id_field = $this->entityType->getKey('id');
      $revision_field = $this->entityType->getKey('revision');

      $this->sqlGroupBy[] = 'base_table.identity';
      $this->sqlFields['base_table.identity'] = ['base_table', 'identity'];
      unset($this->sqlFields["base_table.{$id_field}"]);
      if ($revision_field) {
        unset($this->sqlFields["base_table.{$revision_field}"]);
      }
      else {
        unset($this->sqlFields["base_table.{$id_field}_1"]);
      }

      $version = $this->connection->version();

      if ($this->supportsFirstValueFunction($version)) {
        switch ($this->forceIdentityDistinctGroupingMethod) {
          case "MIN":
          case "MAX":
            $this->sqlQuery->addExpression(
              "FIRST_VALUE(base_table.{$revision_field}) OVER(PARTITION BY base_table.identity ORDER BY base_table.{$id_field} DESC)",
              "base_table__{$revision_field}"
            );
            $this->sqlQuery->addExpression(
              "{$this->forceIdentityDistinctGroupingMethod}(base_table.{$id_field})",
              "base_table__{$id_field}"
            );
        }
      }
      else {
        switch ($this->forceIdentityDistinctGroupingMethod) {
          case 'MIN':
          case 'MAX':
            $this->queryNeedsIdVidSplit = TRUE;
            $this->sqlQuery->addExpression(
              "{$this->forceIdentityDistinctGroupingMethod}(CONCAT(base_table.{$id_field}, '.', base_table.{$revision_field}))",
              "id_vid"
            );
        }
      }
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSqlField($field, $langcode) {
    if ($field === 'identity') {
      return 'base_table.identity';
    }

    return parent::getSqlField($field, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  protected function result() {
    if ($this->count) {
      return $this->sqlQuery->countQuery()->execute()->fetchField();
    }

    if ($this->queryNeedsIdVidSplit) {
      $wrapper_query = $this->connection->select($this->sqlQuery, 'sub');
      $wrapper_query->addExpression("SUBSTRING_INDEX(SUBSTRING_INDEX(sub.id_vid, '.', 2), '.', -1)", 'vid');
      $wrapper_query->addExpression("SUBSTRING_INDEX(sub.id_vid, '.', 1)", "id");

      return $wrapper_query->execute()->fetchAllKeyed();
    }

    return $this->sqlQuery->execute()->fetchAllKeyed(count($this->sqlQuery->getFields()), count($this->sqlQuery->getFields()) + 1);
  }

  /**
   * Check wheterh the database service is at a version that supports
   * FIRST_VALUE or LAST_VALUE
   *
   * @param $version
   */
  protected function supportsFirstValueFunction($version) {
    // @todo: Work out how the hell first value works!
    return FALSE;
    if (stripos($version, 'mariadb')) {
      // For reasons, mariadb prefixes its version with 5.5.5-. We strip this
      // off before checking the version of mariadb.
      // https://github.com/MariaDB/server/blob/f6633bf058802ad7da8196d01fd19d75c53f7274/include/mysql_com.h#L42.
      list(, $real_version) = explode("-", $version, 2);
      if (version_compare($real_version, "10.2") >= 0) {
        return TRUE;
      }

      return FALSE;
    }
    else if ($this->connection->driver() == "mysql") {
      if (version_compare($version, "8.0") >= 0) {
        return TRUE;
      }

      return FALSE;
    }

    return FALSE;
  }
}
