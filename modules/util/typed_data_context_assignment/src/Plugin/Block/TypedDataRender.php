<?php

namespace Drupal\typed_data_context_assignment\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\TypedData\PrimitiveBase;

/**
 * Block to render typed data.
 *
 * @Block(
 *   id = "typed_data_render",
 *   admin_label = @Translation("Display Any Data"),
 *   context_definitions = {
 *     "data" = @ContextDefinition("any", label = @Translation("The Data")),
 *   }
 * );
 *
 * @todo Implement typed data renderer plugins and move to typed_data_reference
 *
 * @package Drupal\typed_data_context_assignment\Plugin\Block
 */
class TypedDataRender extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $data = $this->getContext('data')->getContextData();
    $build = [];

    if ($data instanceof PrimitiveBase) {
      $build = [
        '#type' => 'markup',
        '#markup' => Markup::create($data->getValue()),
      ];
    }

    return $build;
  }

}
