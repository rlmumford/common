<?php

namespace Drupal\rlmcrm_ui\Menu;

use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RouteMatchInterface;

class AddRelationshipLocalAction extends LocalActionDefault {

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters(RouteMatchInterface $route_match) {
    $contact = $route_match->getParameter('user');
    $this->pluginDefinition['route_parameters']['relationship_type'] = 'individual_organisation';
    $this->pluginDefinition['route_parameters']['tail_id'] = $contact->id();
    $this->pluginDefinition['route_parameters']['head_id'] = $contact->id();
    return parent::getRouteParameters($route_match);
  }

}
