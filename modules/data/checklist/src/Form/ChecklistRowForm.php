<?php

namespace Drupal\checklist\Form;

use Drupal\checklist\ChecklistInterface;
use Drupal\checklist\ChecklistTempstoreRepository;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\BaseFormIdInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ChecklistRowForm extends FormBase implements BaseFormIdInterface {

  /**
   * @var \Drupal\Core\Plugin\PluginFormFactoryInterface
   */
  protected $pluginFormFactory;

  /**
   * @var \Drupal\checklist\ChecklistTempstoreRepository
   */
  protected $checklistTempstoreRepo;

  /**
   * @var \Drupal\checklist\Entity\ChecklistItemInterface
   */
  protected $item;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin_form.factory'),
      $container->get('checklist.tempstore_repository')
    );
  }

  public function __construct(
    PluginFormFactoryInterface $plugin_form_factory,
    ChecklistTempstoreRepository $checklist_tempstore_repository
  ) {
    $this->pluginFormFactory = $plugin_form_factory;
    $this->checklistTempstoreRepo = $checklist_tempstore_repository;
  }

  public function setChecklistItem(ChecklistItemInterface $item) {
    $this->item = $item;
  }

  public function getChecklistItem() : ?ChecklistItemInterface {
    return $this->item;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ci_'.$this->item->checklist->checklist->getKey().'__'.$this->item->getName().'_row_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    return 'ci_row_form';
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
  public function buildForm(array $form, FormStateInterface $form_state) {
    $handler = $this->item->getHandler();
    if (!$handler->hasFormClass('row')) {
      throw new \Exception('Checklist item plugins MUST have a row form.');
    }

    /** @var \Drupal\Core\Plugin\PluginFormInterface $plugin_form */
    $plugin_form = $this->pluginFormFactory->createInstance($handler, 'row');
    return $plugin_form->buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $plugin_form = $this->pluginFormFactory->createInstance($this->item->getHandler(), 'row');
    $plugin_form->validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $checklist = $this->item->checklist->checklist;
    $checklist = $this->checklistTempstoreRepo->get($checklist);
    $item = $checklist->getItem($this->item->getName());
    $this->item = $item;

    $plugin_form = $this->pluginFormFactory->createInstance(
      $this->item->getHandler(), 'row'
    );
    $plugin_form->submitConfigurationForm($form, $form_state);

    $this->checklistTempstoreRepo->set($checklist);
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array|AjaxResponse
   */
  public static function onCompleteAjaxCallback(array &$form, FormStateInterface $form_state) {
    if (!$form_state->isExecuted()) {
      return $form;
    }

    /** @var \Drupal\Core\Render\MainContent\MainContentRendererInterface $ajax_renderer */
    $ajax_renderer = \Drupal::service('main_content_renderer.ajax');

    // If the form is rebuilding then we need to still render it, but we have to
    // do it directly so that we can add more to the commands list.
    if ($form_state->isRebuilding()) {
      $response = $ajax_renderer->renderResponse($form, \Drupal::request(), \Drupal::routeMatch());
    }
    else {
      $response = new AjaxResponse();
    }

    /** @var \Drupal\Core\Ajax\AjaxResponse $response */

    // @todo: Reload any dependent forms.
    // @todo: Close any resource or form panes.

    return $response;
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public static function onReverseAjaxCallback(array &$form, FormStateInterface $form_state) {
    if (!$form_state->isExecuted()) {
      return $form;
    }

    /** @var \Drupal\Core\Render\MainContent\MainContentRendererInterface $ajax_renderer */
    $ajax_renderer = \Drupal::service('main_content_renderer.ajax');

    // If the form is rebuilding then we need to still render it, but we have to
    // do it directly so that we can add more to the commands list.
    if ($form_state->isRebuilding()) {
      $response = $ajax_renderer->renderResponse($form, \Drupal::request(), \Drupal::routeMatch());
    }
    else {
      $response = new AjaxResponse();
    }

    /** @var \Drupal\Core\Ajax\AjaxResponse $response */

    // @todo: Reload any dependent forms.
    // @todo: Close any resource or form panes.

    return $response;
  }


}
