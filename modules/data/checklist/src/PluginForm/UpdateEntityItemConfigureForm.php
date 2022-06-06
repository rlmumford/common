<?php

namespace Drupal\checklist\PluginForm;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for configuring update entity items.
 */
class UpdateEntityItemConfigureForm extends ContextAwareItemConfigureForm implements ContainerInjectionInterface {

  /**
   * The checklist item handler.
   *
   * @var \Drupal\checklist\Plugin\ChecklistItemHandler\UpdateEntity
   */
  protected $plugin;

  /**
   * The display repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected EntityDisplayRepositoryInterface $displayRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_display.repository')
    );
  }

  /**
   * Create an instance of this plugin form.
   *
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository
   *   The entity display repository service.
   */
  public function __construct(EntityDisplayRepositoryInterface $display_repository) {
    $this->displayRepository = $display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['form_mode'] = [
      '#type' => 'select',
      '#title' => new TranslatableMarkup('Form Mode'),
      '#options' => $this->displayRepository->getFormModeOptions($this->plugin->getPluginDefinition()['entity_type']),
      '#default_value' => $this->plugin->getConfiguration()['form_mode'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $configuration = $this->plugin->getConfiguration();
    $configuration['form_mode'] = $form_state->getValue('form_mode');
    $this->plugin->setConfiguration($configuration);
  }
}
