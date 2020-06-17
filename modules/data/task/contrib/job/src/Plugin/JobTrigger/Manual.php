<?php

namespace Drupal\task_job\Plugin\JobTrigger;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Class Manual
 *
 * @JobTrigger(
 *   id = "manual",
 *   label = @Translation("Manual"),
 *   description = @Translation("This job can be manually created."),
 * )
 *
 * @package Drupal\task_job\Plugin\JobTrigger
 */
class Manual extends JobTriggerBase {

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return new TranslatableMarkup('Manual');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return new TranslatableMarkup('This job can be manually created by a user.');
  }

}
