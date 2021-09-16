<?php

namespace Drupal\checklist\Form;

use Drupal\checklist\ChecklistInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Completion form for checklists.
 *
 * @package Drupal\checklist\Form
 */
class ChecklistCompleteForm extends FormBase {

  /**
   * The plugin form factory.
   *
   * @var \Drupal\Core\Plugin\PluginFormFactoryInterface
   */
  protected $pluginFormFactory;

  /**
   * The checklist.
   *
   * @var \Drupal\checklist\ChecklistInterface
   */
  protected $checklist;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin_form.factory')
    );
  }

  /**
   * ChecklistCompleteForm constructor.
   *
   * @param \Drupal\Core\Plugin\PluginFormFactoryInterface $plugin_form_factory
   *   The plugin form factory service.
   */
  public function __construct(PluginFormFactoryInterface $plugin_form_factory) {
    $this->pluginFormFactory = $plugin_form_factory;
  }

  /**
   * Set the checklist being completed.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The checklist.
   *
   * @return $this
   */
  public function setChecklist(ChecklistInterface $checklist) {
    $this->checklist = $checklist;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'checklist_'.$this->checklist->getType()->getPluginId().'_completion_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form_state->set('checklist', $this->checklist);

    $form['actions'] = [
      '#type' => 'actions',
      'complete' => [
        '#type' => 'submit',
        '#value' => $this->t('Complete'),
        '#submit' => ['::submitForm'],
        '#validate' => ['::validateForm'],
      ],
    ];

    $type = $this->checklist->getType();
    if (
      $type instanceof PluginWithFormsInterface &&
      $type->hasFormClass('complete')
    ) {
      $plugin_form = $this->pluginFormFactory->createInstance($type, 'complete');
      $form = $plugin_form->buildConfigurationForm($form, $form_state);
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $type = $this->checklist->getType();
    if (
      $type instanceof PluginWithFormsInterface &&
      $type->hasFormClass('complete')
    ) {
      $plugin_form = $this->pluginFormFactory->createInstance($type, 'complete');
      $form = $plugin_form->validateConfigurationForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $type = $this->checklist->getType();

    if (
      $type instanceof PluginWithFormsInterface &&
      $type->hasFormClass('complete')
    ) {
      $plugin_form = $this->pluginFormFactory->createInstance($type, 'complete');
      $form = $plugin_form->submitConfigurationForm($form, $form_state);
    }

    $this->checklist->complete();
  }

}
