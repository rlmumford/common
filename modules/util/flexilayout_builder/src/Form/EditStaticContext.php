<?php

namespace Drupal\flexilayout_builder\Form;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\ctools\Context\EntityLazyLoadContext;
use Drupal\flexilayout_builder\Plugin\SectionStorage\DisplayWideConfigSectionStorageInterface;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AddStaticContext
 *
 * @package Drupal\flexilayout_builder\Form
 */
class EditStaticContext extends FormBase {
  use AjaxFormHelperTrait;
  use LayoutRebuildTrait;

  /**
   * @var \Drupal\flexilayout_builder\Plugin\SectionStorage\DisplayWideConfigSectionStorageInterface
   */
  protected $sectionStorage;

  /**
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return \Drupal\Core\Form\FormBase|\Drupal\flexilayout_builder\Form\AddStaticContext
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('class_resolver'),
      $container->get('layout_builder.tempstore_repository')
    );
  }

  /**
   * AddStaticContext constructor.
   *
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   */
  public function __construct(ClassResolverInterface $class_resolver, LayoutTempstoreRepositoryInterface $layout_tempstore_repository) {
    $this->classResolver = $class_resolver;
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
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
    return 'edit_static_context_form';
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
  public function buildForm(array $form, FormStateInterface $form_state, DisplayWideConfigSectionStorageInterface $section_storage = NULL, $machine_name = NULL) {
    $this->sectionStorage = $section_storage;
    $static_contexts = $this->sectionStorage->getConfig('static_context');
    $context = $static_contexts[$machine_name];
    $data_type = $context['type'];

    $form['data_type'] = [
      '#type' => 'value',
      '#value' => $data_type,
    ];
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#required' => TRUE,
      '#default_value' => $context['label'],
    ];
    $form['machine_name'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Machine Name'),
      '#required' => TRUE,
      '#default_value' => $machine_name,
      '#disabled' => TRUE,
      '#maxlength' => 128,
      '#machine_name' => [
        'source' => ['label'],
        'exists' => [$this, 'contextExists'],
      ],
    ];
    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $context['description'],
    ];
    if (strpos($data_type, 'entity:') === 0) {
      list(, $entity_type) = explode(':', $data_type);
      $context_object = new EntityLazyLoadContext(
        new ContextDefinition($data_type, $context['label']),
        \Drupal::service('entity.repository'),
        $context['value']
      );
      /** @var EntityAdapter $entity */
      $form['context_value'] = [
        '#type' => 'entity_autocomplete',
        '#required' => TRUE,
        '#target_type' => $entity_type,
        '#default_value' => $context_object->getContextValue(),
        '#title' => $this->t('Select entity'),
      ];
    }
    else {
      $form['context_value'] = [
        '#title' => $this->t('Set a context value'),
        '#type' => 'textfield',
        '#required' => TRUE,
      ];
    }
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Context'),
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
    $static_context = $this->sectionStorage->getConfig('static_context');
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
    $static_contexts = $this->sectionStorage->getConfig('static_context');
    $static_contexts[$values['machine_name']] = [
      'type' => $values['data_type'],
      'label' => $values['label'],
      'description' => $values['description'],
      'value' => $values['context_value'],
    ];
    $this->sectionStorage->setConfig('static_context', $static_contexts);

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
