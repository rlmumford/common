<?php

namespace Drupal\rlm_material\Plugin\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a block that displays the drawer title
 *
 * @Block(
 *   id = "rlm_drawer_title",
 *   admin_label = @Translation("Drawer Title"),
 * )
 */
class DrawerTitle extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['close'] = [
      '#prefix' => '<div id="drawer-menu-close">',
      '#suffix' => '</div>',
      '#type' => 'html_tag',
      '#tag' => 'a',
      '#attributes' => [
        'href' => '#',
      ],
      '#value' => '<i class="material-icons drawer-icon">arrow_back</i>',
    ];
    $build['title'] = [
      '#prefix' => '<div id="drawer-title">',
      '#suffix' => '</div>',
      '#markup' => new TranslatableMarkup('Menu'),
    ];

    return $build;
  }

}
