<?php

namespace Drupal\task_job\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\task_job\JobInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to add a checklist item.
 */
class JobAddChecklistItemForm extends JobPluginFormBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('task_job.tempstore_repository'),
      $container->get('plugin_form.factory'),
      $container->get('plugin.manager.checklist_item_handler'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'job_add_checklist_item_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    JobInterface $task_job = NULL,
    $handler = NULL,
    $handler_config = []
  ) {
    $form = parent::buildForm(
      $form,
      $form_state,
      $task_job,
      $handler,
      $handler_config
    );

    unset($form['message']);

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#size' => 10,
      '#weight' => -10,
      '#required' => TRUE,
    ];
    if ($default_prefix = $this->config('task_checklist.defaults')->get('ci_name_prefix')) {
      $form['name']['#default_value'] = $default_prefix . str_pad(
          count($form_state->get('job')->getChecklistItems()) + 1,
          2,
          '0',
          STR_PAD_LEFT
        );
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#weight' => -8,
    ];

    $form['placeholders'] = [
      '#type' => 'available_placeholders',
      '#title' => $this->t('Placeholders and Filters'),
      '#contexts' => [
        'checklist_item' => new EntityContext(
          EntityContextDefinition::fromEntityTypeId('checklist_item', 'This Checklist Item')
            ->addConstraint('Bundle', 'job')
        ),
        'task' => new EntityContext(
          EntityContextDefinition::fromEntityTypeId('task', 'The Task'),
          \Drupal::entityTypeManager()->getStorage('task')->create([
            'job' => $task_job,
          ])
        ),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    /** @var \Drupal\Component\Plugin\PluginInspectionInterface $plugin */
    $plugin = $form_state->get('plugin');
    $job = $form_state->get('job');
    $checklist_items = $job->get('default_checklist');
    $checklist_items[$form_state->getValue('name')] = [
      'name' => $form_state->getValue('name'),
      'label' => $form_state->getValue('label'),
      'handler' => $plugin->getPluginId(),
      'handler_configuration' => $plugin->getConfiguration(),
    ];
    $job->set('default_checklist', $checklist_items);

    $this->tempstoreRepository->set($job);
  }

}
