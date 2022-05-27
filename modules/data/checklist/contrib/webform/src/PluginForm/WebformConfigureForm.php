<?php

namespace Drupal\checklist_webform\PluginForm;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for the webform checklist item handler.
 */
class WebformConfigureForm extends PluginFormBase implements ContainerInjectionInterface {
  use DependencySerializationTrait;

  /**
   * The plugin.
   *
   * @var \Drupal\checklist_webform\Plugin\ChecklistItemHandler\Webform
   */
  protected $plugin;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Create a plugin form to configure the webform action.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $available_webforms = [];
    foreach ($this->entityTypeManager->getStorage('webform')->loadMultiple(NULL) as $id => $webform) {
      $available_webforms[$id] = $webform->label();
    }

    $form['webform'] = [
      '#type' => 'select',
      '#title' => new TranslatableMarkup('Webform'),
      '#description' => new TranslatableMarkup(
        'The webform to use in this checklist item. To create a new form @link.',
        [
          '@link' => Link::createFromRoute(
            'click here',
            'entity.webform.add_form'
          )
        ]
      ),
      '#options' => $available_webforms,
      '#default_value' => $this->plugin->getConfiguration()['webform'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $configuration = $this->plugin->getConfiguration();
    $configuration['webform'] = $form_state->getValue('webform');
    $this->plugin->setConfiguration($configuration);
  }

}
