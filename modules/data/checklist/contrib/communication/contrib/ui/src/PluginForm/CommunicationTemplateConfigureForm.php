<?php

namespace Drupal\checklist_communication_ui\PluginForm;

use Drupal\checklist_communication_ui\Form\OperationConfiguration;
use Drupal\checklist_entity_template_ui\PluginForm\TemplateItemConfigureForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds the selected follow-up operation to each existing template candidate.
 */
class CommunicationTemplateConfigureForm extends TemplateItemConfigureForm {

  /**
   * The shared operation configuration controls.
   */
  protected OperationConfiguration $operationConfiguration;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->operationConfiguration = $container->get('checklist_communication_ui.operation_configuration');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $settings = array_values($this->plugin->getConfiguration()['templates']);
    foreach ($form['templates'] as $index => &$element) {
      if (is_int($index) && isset($element['template'])) {
        $element['operation'] = $this->operationConfiguration->build($settings[$index]['operation'] ?? []);
      }
    }
    unset($element);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function candidateConfiguration(array $candidate, array $row, array &$element, FormStateInterface $form_state): array {
    $settings = $row['operation'];
    $settings['confirm'] = (bool) $settings['confirm'];
    $this->operationConfiguration->validate($element['operation'], $settings, $form_state);
    $candidate['operation'] = $settings;
    return $candidate;
  }

}
