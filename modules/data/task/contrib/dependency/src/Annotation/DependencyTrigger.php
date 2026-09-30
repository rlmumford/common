<?php

namespace Drupal\task_dependency\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an event matcher independently of its task or job consumer.
 *
 * @Annotation
 */
class DependencyTrigger extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable label.
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

}
