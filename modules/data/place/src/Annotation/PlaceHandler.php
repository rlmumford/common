<?php

namespace Drupal\place\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a PlaceHandler plugin annotation object.
 *
 * Plugin Namespace: Plugin\PlaceHandler.
 *
 * @see \Drupal\place\Plugin\PlaceHandler\PlaceHandlerInterface
 * @see \Drupal\place\Plugin\PlaceHandler\PlaceHandlerBase
 *
 * @ingroup plugin_api
 *
 * @Annotation
 */
class PlaceHandler extends Plugin {

  /**
   * The id of this handler.
   *
   * @var string
   */
  public $id;

  /**
   * The label of this handler.
   *
   * @var string
   */
  public $label;
}
