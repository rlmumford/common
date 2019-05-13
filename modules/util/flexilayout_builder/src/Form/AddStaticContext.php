<?php

namespace Drupal\flexilayout_builder\Form;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\DefaultsSectionStorageInterface;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AddStaticContext
 *
 * @package Drupal\flexilayout_builder\Form
 */
class AddStaticContext extends FormBase {
  use AjaxFormHelperTrait;
  use LayoutRebuildTrait;

  /**
   * @var \Drupal\layout_builder\DefaultsSectionStorageInterface
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
  public function buildForm(array $form, FormStateInterface $form_state, SectionStorageInterface $section_storage = NULL, $data_type = NULL) {
    if (!$section_storage instanceof ThirdPartySettingsInterface) {
      \Drupal::messenger()->addError(new TranslatableMarkup('Only Section Storages with third party settings can have configurable contexts.'));
      return $form;
    }

    $this->sectionStorage = $section_storage;

    $form['data_type'] = [
      '#type' => 'value',
      '#value' => $data_type,
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
    if (strpos($data_type, 'entity:') === 0) {
      list(, $entity_type) = explode(':', $data_type);
      /** @var EntityAdapter $entity */
      $form['context_value'] = [
        '#type' => 'entity_autocomplete',
        '#required' => TRUE,
        '#target_type' => $entity_type,
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
      '#value' => $this->t('Add Context'),
      '#button_type' => 'primary',
      '#submit' => [
        '::submitForm',
      ],
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
    $static_context = $this->sectionStorage->getThirdPartySetting('flexilayout_builder', 'static_context');
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
    $static_contexts = $this->sectionStorage->getThirdPartySetting('flexilayout_builder', 'static_context');
    $static_contexts[$values['machine_name']] = [
      'type' => $values['data_type'],
      'label' => $values['label'],
      'description' => $values['description'],
      'value' => $values['context_value'],
    ];
    $this->sectionStorage->setThirdPartySetting('flexilayout_builder', 'static_context', $static_contexts);

    dpm($this->sectionStorage);
    dpm($this->sectionStorage->getThirdPartySettings('flexilayout_builder'));

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
}
