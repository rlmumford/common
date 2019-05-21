<?php
/**
 * Created by PhpStorm.
 * User: Rob
 * Date: 21/05/2019
 * Time: 11:40
 */

namespace Drupal\rlmcrm_ui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;

/**
 * Class ContactsHeaderInformation
 *
 * @Block(
 *   id = "contacts_header_information",
 *   admin_label = @Translation("Contact Header Information"),
 * )
 *
 * @package Drupal\rlmcrm_ui\Plugin\Block
 */
class ContactsHeaderInformation extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $cache_contexts = parent::getCacheContexts();
    $cache_contexts = Cache::mergeContexts($cache_contexts, ['user']);
    return $cache_contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $route_match = \Drupal::routeMatch();

    // Only show on selected routes.
    $supported_routes = [
      'rlmcrm_ui.contact',
      'rlmcrm_ui.contact.communication',
      'rlmcrm_ui.contact.employer',
      'rlmcrm_ui.contact.organisations',
      'rlmcrm_ui.contact.individuals',
    ];
    if (!in_array($route_match->getRouteName(), $supported_routes)) {
      return [];
    }

    $user = $route_match->getParameter('user');
    $role_ids = $user->getRoles();
    $roles = \Drupal::entityTypeManager()
      ->getStorage('user_role')
      ->loadMultiple($role_ids);
    $labels = [];
    foreach ($roles as $role) {
      $labels[] = $role->label();
    }

    return [
      '#markup' => implode(', ', $labels),
    ];
  }
}
