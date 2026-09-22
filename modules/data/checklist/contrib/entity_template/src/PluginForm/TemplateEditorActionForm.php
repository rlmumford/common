<?php

namespace Drupal\checklist_entity_template\PluginForm;

use Drupal\checklist\PluginForm\CustomFormObjectClassInterface;
use Drupal\checklist_entity_template\Form\TemplateEditorForm;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Selects the shared-session form host instead of legacy checklist tempstore.
 */
class TemplateEditorActionForm extends PluginFormBase implements CustomFormObjectClassInterface {

  /**
   * {@inheritdoc}
   */
  public static function getFormObjectClass(PluginInspectionInterface $plugin, string $default_class): string {
    return TemplateEditorForm::class;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    throw new \LogicException('Use the shared template editor form host.');
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    throw new \LogicException('Use the shared template editor form host.');
  }

}
