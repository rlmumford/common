<?php

namespace Drupal\place\Annotation;

use Drupal\Component\Annotation\Plugin;

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
