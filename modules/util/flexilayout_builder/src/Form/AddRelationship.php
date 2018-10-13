<?php

namespace Drupal\flexilayout_builder\Form;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ctools\Plugin\RelationshipManagerInterface;
use Drupal\flexilayout_builder\Controller\FlexiLayoutBuilderController;
use Drupal\flexilayout_builder\Plugin\Relationship\ConfigurableRelationshipInterface;
use Drupal\flexilayout_builder\Plugin\SectionStorage\DisplayWideConfigSectionStorageInterface;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AddStaticContext
 *
 * @package Drupal\flexilayout_builder\Form
 */
class AddRelationship extends FormBase {
  use AjaxFormHelperTrait;
  use LayoutRebuildTrait;
  use ContextAwarePluginAssignmentTrait;
  use StringTranslationTrait;

  /**
   * @var \Drupal\flexilayout_builder\Plugin\SectionStorage\DisplayWideConfigSectionStorageInterface
   */
  protected $sectionStorage;

  /**
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * @var \Drupal\ctools\Plugin\RelationshipManagerInterface
   */
  protected $relationshipManager;

  /**
   * @var \Drupal\Core\Plugin\PluginFormFactoryInterface
   */
  protected $pluginFormFactory;

  /**
   * @var \Drupal\ctools\Plugin\RelationshipInterface
   */
  protected $relationship;

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return \Drupal\Core\Form\FormBase|\Drupal\flexilayout_builder\Form\AddStaticContext
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('class_resolver'),
      $container->get('layout_builder.tempstore_repository'),
      $container->get('plugin.manager.ctools.relationship'),
      $container->get('plugin_form.factory')
    );
  }

  /**
   * AddStaticContext constructor.
   *
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   */
  public function __construct(ClassResolverInterface $class_resolver, LayoutTempstoreRepositoryInterface $layout_tempstore_repository, RelationshipManagerInterface $relationship_manager, PluginFormFactoryInterface $plugin_form_factory) {
    $this->classResolver = $class_resolver;
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->relationshipManager = $relationship_manager;
    $this->pluginFormFactory = $plugin_form_factory;
  }

  /**
   * Returns a unique string identifying the form.
   *
   * The returned ID should be a unique string that can be a valid PHP function
   * name, since it's used in hook implementation names such as
   * hook_form_FORM_ID_alter().
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'add_static_context_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, DisplayWideConfigSectionStorageInterface $section_storage = NULL, $plugin = NULL) {
    return $this->doBuildForm($form, $form_state, $section_storage, 'add', $plugin);
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function doBuildForm(array $form, FormStateInterface $form_state, DisplayWideConfigSectionStorageInterface $section_storage = NULL, $op = 'edit', $plugin = NULL) {
    $this->sectionStorage = $section_storage;

    if ($op == 'edit') {
      $relationships = $this->sectionStorage->getConfig('relationships');
      $relationship_config = $relationships[$plugin];
      $machine_name = $plugin;
      $plugin = $relationship_config['plugin'];
      $this->relationship = $this->relationshipManager->createInstance($relationship_config['plugin'], $relationship_config['settings'] ?: []);
    }
    else {
      $this->relationship = $this->relationshipManager->createInstance($plugin);
      $machine_name = NULL;
    }


    $form['#tree'] = TRUE;
    $form['plugin'] = [
      '#type' => 'value',
      '#value' => $plugin,
    ];
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#required' => TRUE,
      '#default_value' => isset($relationship_config['label']) ? $relationship_config['label'] : '',
    ];
    $form['machine_name'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Machine Name'),
      '#required' => TRUE,
      '#maxlength' => 128,
      '#default_value' => $machine_name,
      '#disabled' => !empty($machine_name),
      '#machine_name' => [
        'source' => ['label'],
        'exists' => [$this, 'contextExists'],
      ],
    ];
    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => isset($relationship_config['description']) ? $relationship_config['description'] : '',
    ];

    if ($this->relationship instanceof ConfigurableRelationshipInterface) {
      $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
      $form['settings'] = [];
      $form['settings'] = $this->pluginFormFactory->createInstance($this->relationship, 'configure')->buildConfigurationForm($form['settings'], $subform_state);
    }
    else {
      $form['settings'] = [
        '#type' => 'container',
        'context_mapping' => $this->addContextAssignmentElement($this->relationship, $this->sectionStorage->getContexts()),
       ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Relationship'),
      '#button_type' => 'primary',
    ];
    if ($this->isAjax()) {
      $form['submit']['#ajax']['callback'] = '::ajaxSubmit';
    }

    return $form;
  }

  /**
   * @param $value
   * @param array $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return bool
   */
  public function contextExists($value, array $element, FormStateInterface $form_state) {
    $static_context = $this->sectionStorage->getConfig('relationships');
    return !empty($static_context[$value]);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($this->relationship instanceof ConfigurableRelationshipInterface) {
      $subform_state = SubformState::createForSubform($form['settings'], $form,
        $form_state);
      $this->pluginFormFactory
        ->createInstance($this->relationship, 'configure')
        ->validateConfigurationForm($form['settings'], $subform_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->relationship instanceof ConfigurableRelationshipInterface) {
      // Call the plugin submit handler.
      $subform_state = SubformState::createForSubform(
        $form['settings'],
        $form,
        $form_state
      );
      $this->pluginFormFactory->createInstance($this->relationship, 'configure')
        ->submitConfigurationForm(
          $form,
          $subform_state
        );
      $this->relationship->setContextMapping($subform_state->getValue('context_mapping', []));

      $configuration = $this->relationship->getConfiguration();
    }
    else {
      $configuration = $form_state->getValue('settings');
    }

    $values = $form_state->getValues();
    $relationships = $this->sectionStorage->getConfig('relationships');
    $relationships[$values['machine_name']] = [
      'plugin' => $values['plugin'],
      'label' => $values['label'],
      'description' => $values['description'],
      'settings' => $configuration,
    ];
    $this->sectionStorage->setConfig('relationships', $relationships);

    $this->layoutTempstoreRepository->set($this->sectionStorage);
    $form_state->setRedirectUrl($this->sectionStorage->getLayoutBuilderUrl());
  }

  /**
   * Allows the form to respond to a successful AJAX submission.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response.
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    return $this->rebuildAndClose($this->sectionStorage);
  }

  /**
   * Rebuilds the layout.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response to either rebuild the layout and close the dialog, or
   *   reload the page.
   */
  protected function rebuildLayout(SectionStorageInterface $section_storage) {
    $response = new AjaxResponse();
    $layout_controller = $this->classResolver->getInstanceFromDefinition(FlexiLayoutBuilderController::class);
    $layout = $layout_controller->layout($section_storage, TRUE);
    $response->addCommand(new ReplaceCommand('#layout-builder', $layout));
    return $response;
  }
}
