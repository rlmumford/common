<?php
/**
 * Created by PhpStorm.
 * User: Rob
 * Date: 08/11/2018
 * Time: 14:36
 */

namespace Drupal\rlm_material\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block that displays the menu toggle hamburger.
 *
 * @Block(
 *   id = "rlm_menu_toggle",
 *   admin_label = @Translation("Menu Toggle"),
 * )
 */
class MenuToggle extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#prefix' => '<div id="navbar-menu-toggle">',
      '#markup' => '<a href="#"><i class="material-icons navbar-icon">menu</i></a>',
      '#suffix' => '</div>',
    ];
  }

}
