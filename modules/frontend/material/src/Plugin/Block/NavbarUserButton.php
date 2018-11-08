<?php

namespace Drupal\rlm_material\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block that displays page header.
 *
 * @Block(
 *   id = "rlm_user_button",
 *   admin_label = @Translation("Navbar User Button"),
 * )
 */
class NavbarUserButton extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $cache = [];

    $class = \Drupal::currentUser()->isAuthenticated() ? 'user-authenticated' : 'user-anonymous';
    $title = \Drupal::currentUser()->isAuthenticated() ? 'Your Dashboard' : 'Log-in';
    $markup = '<a title="'.$title.'" href="/user" rel="nofollow"><i class="material-icons navbar-icon '.$class.'">account_circle</i></a>';

    if (\Drupal::moduleHandler()->moduleExists('commerce_cart') && ($cart = \Drupal::service('commerce_cart.cart_provider')->getCart('default'))) {
      /** @noinspection PhpUndefinedNamespaceInspection */
      /** @var \Drupal\commerce_order\Entity\OrderInterface $cart */
      $item_count = count($cart->getItems());
      $items_class = $item_count > 0 ? 'has-items has-'.$item_count.'-items' : 'no-items';
      $markup = '<a title="Your Cart" href="/cart" rel="nofollow"><i item-count="'.$item_count.'" class="material-icons navbar-icon cart-icon '.$items_class.'">shopping_basket</i></a>' . $markup;

      $cache['contexts'][] = 'cart';
    }

    return [
      '#prefix' => '<div id="navbar-user-button">',
      '#markup' => $markup,
      '#suffix' => '</div>',
      '#cache' => $cache,
    ];
  }

}
