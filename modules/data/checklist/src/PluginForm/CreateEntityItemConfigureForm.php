<?php

namespace Drupal\checklist\PluginForm;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CreateEntityItemConfigureForm extends PluginFormBase implements ContainerInjectionInterface {
  use StringTranslationTrait;

  /**
   * @var \Drupal\checklist\Plugin\ChecklistItemHandler\CreateEntity
   */
  protected $plugin;

  /**
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info')
    );
  }

  public function __construct(EntityTypeBundleInfo $entity_type_bundle_info) {
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $configuration = $this->plugin->getConfiguration();

    $bundle_options = [
      '__select' => $this->t('Allow the user to select'),
    ];
    foreach ($this->entityTypeBundleInfo->getBundleInfo($this->plugin->getEntityType()->id()) as $bundle_name => $info) {
      $bundle_options[$bundle_name] = $info['label'];
    }

    $form['bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Bundle'), // @todo: Change the title bundle to be entity type specific.
      '#options' => $bundle_options,
      '#default_value' => $configuration['bundle'],
    ];

    $form['show_form'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show a Form'),
      '#default_value' => !empty($configuration['show_form']),
    ];

    $form['form_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Form Mode'),
      '#default_value' => $configuration['form_mode'],
      '#options' => [
        'add' => $this->t('Add'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $configuration = $this->plugin->getConfiguration();
    $configuration['bundle'] = $form_state->getValue('bundle');
    $configuration['show_form'] = (bool) $form_state->getValue('show_form');
    $configuration['form_mode'] = $form_state->getValue('form_mode');
    $this->plugin->setConfiguration($configuration);
  }
}
