<?php
/**
 * Created by PhpStorm.
 * User: Rob
 * Date: 17/06/2020
 * Time: 11:54
 */

namespace Drupal\project\Plugin\JobTrigger;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\task_job\Plugin\JobTrigger\Manual as BaseManual;

/**
 * Class Manual
 *
 * @JobTrigger(
 *   id = "project_manual",
 *   label = @Translation(Project Manual"),
 *   description = @Translation("This job can be manually created in relation to a project."),
 *   context = {
 *     "project" = @ContextDefinition("entity:project",
 *       label = @Translation("Project")
 *     )
 *   }
 * )
 *
 * @package Drupal\project\Plugin\JobTrigger
 */
class Manual extends BaseManual {

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return new TranslatableMarkup('Manual from Project');
  }

}
