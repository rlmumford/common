<?php

namespace Drupal\checklist_entity_template\PluginForm;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\PluginForm\StartableItemRowForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Opens interactive editors while showing automatic items as read-only status.
 */
class TemplateItemRowForm extends StartableItemRowForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    if ($this->plugin->getMethod() === ChecklistItemInterface::METHOD_AUTO) {
      $form['checkbox']['#disabled'] = TRUE;
      $form['start']['#access'] = FALSE;
    }
    return $form;
  }

}
