<?php

namespace Drupal\identity\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Class IdentityDataClass
 *
 * @package Drupal\identity\Annotation
 *
 * @Annotation
 */
class IdentityDataClass extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The plugin label.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

}
