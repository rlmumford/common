<?php
/**
 * Created by PhpStorm.
 * User: Rob
 * Date: 30/07/2019
 * Time: 17:22
 */

namespace Drupal\identity\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Class IdentityDataType
 *
 * @package Drupal\identity\Annotation
 *
 * @Annotation
 */
class IdentityDataType extends Plugin {

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
