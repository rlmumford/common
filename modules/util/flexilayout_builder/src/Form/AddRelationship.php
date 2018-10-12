<?php

namespace Drupal\flexilayout_builder\Form;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ctools\Plugin\RelationshipManagerInterface;
use Drupal\flexilayout_builder\Controller\FlexiLayoutBuilderController;
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
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return \Drupal\Core\Form\FormBase|\Drupal\flexilayout_builder\Form\AddStaticContext
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('class_resolver'),
      $container->get('layout_builder.tempstore_repository'),
      $container->get('plugin.manager.ctools.relationship')
    );
  }

  /**
   * AddStaticContext constructor.
   *
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   */
  public function __construct(ClassResolverInterface $class_resolver, LayoutTempstoreRepositoryInterface $layout_tempstore_repository, RelationshipManagerInterface $relationship_manager) {
    $this->classResolver = $class_resolver;
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->relationshipManager = $relationship_manager;
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
  public function buildForm(array $form, FormStateInterface $form_state, DisplayWideConfigSectionStorageInterface $section_storage = NULL, $plugin = NULL) {
    $this->sectionStorage = $section_storage;

    $form['#tree'] = TRUE;
    $form['plugin'] = [
      '#type' => 'value',
      '#value' => $plugin,
    ];
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#required' => TRUE,
    ];
    $form['machine_name'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Machine Name'),
      '#required' => TRUE,
      '#maxlength' => 128,
      '#machine_name' => [
        'source' => ['label'],
        'exists' => [$this, 'contextExists'],
      ],
    ];
    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
    ];

    $instance = $this->relationshipManager->createInstance($plugin);
    if ($instance instanceof PluginWithFormsInterface) {
      // Get form.
    }
    else {
      $form['settings'] = [
        '#type' => 'container',
        'context_mapping' => $this->addContextAssignmentElement($plugin, $this->sectionStorage->getContexts()),
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
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $relationships = $this->sectionStorage->getConfig('relationships');
    $relationships[$values['machine_name']] = [
      'plugin' => $values['plugin'],
      'label' => $values['label'],
      'description' => $values['description'],
    ];
    $this->sectionStorage->setConfig('relationships', $relationships);

    $this->layoutTempstoreRepository->set($this->sectionStorage);
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
