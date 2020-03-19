<?php
/**
 * Created by PhpStorm.
 * User: Rob
 * Date: 28/11/2019
 * Time: 15:49
 */

namespace Drupal\identity\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;

/**
 * Provides selection for the identity entity.
 *
 * @EntityReferenceSelection(
 *   id = "default:identity",
 *   label = @Translation("Identity selection"),
 *   entity_types = {"identity"},
 *   group = "default",
 *   weight = 1
 * )
 */
class IdentitySelection extends DefaultSelection {

  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $configuration = $this->getConfiguration();
    $target_type = $configuration['target_type'];

    $query = $this->entityTypeManager->getStorage($target_type)->getQuery();

    if (isset($match)) {
      $query->condition($query->orConditionGroup()
        ->condition('personal_name::full_name', $match, $match_operator)
        ->condition('organization_name::org_name', $match, $match_operator)
      );
    }

    // Add entity-access tag.
    $query->addTag($target_type . '_access');

    // Add the Selection handler for system_query_entity_reference_alter().
    $query->addTag('entity_reference');
    $query->addMetaData('entity_reference_selection_handler', $this);

    // Add the sort option.
    if ($configuration['sort']['field'] !== '_none') {
      $query->sort($configuration['sort']['field'], $configuration['sort']['direction']);
    }

    return $query;
  }
}
