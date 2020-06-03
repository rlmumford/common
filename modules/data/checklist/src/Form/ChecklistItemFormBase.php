<?php

namespace Drupal\checklist\Form;

use Drupal\checklist\ChecklistTempstoreRepository;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Core\DependencyInjection\ClassResolver;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\BaseFormIdInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class ChecklistItemFormBase extends FormBase implements BaseFormIdInterface {

  /**
   * What sort of form class to use
   *
   * @var string
   */
  protected $formClass = NULL;

  /**
   * @var \Drupal\checklist\Entity\ChecklistItemInterface
   */
  protected $item;

  /**
   * @var \Drupal\Core\Url
   */
  protected $actionUrl = NULL;

  /**
   * @var \Drupal\Core\Plugin\PluginFormFactoryInterface
   */
  protected $pluginFormFactory;

  /**
   * @var \Drupal\checklist\ChecklistTempstoreRepository
   */
  protected $checklistTempstoreRepo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin_form.factory'),
      $container->get('checklist.tempstore_repository')
    );
  }

  /**
   * ChecklistItemFormBase constructor.
   *
   * @param \Drupal\Core\Plugin\PluginFormFactoryInterface $plugin_form_factory
   * @param \Drupal\checklist\ChecklistTempstoreRepository $checklist_tempstore_repository
   */
  public function __construct(
    PluginFormFactoryInterface $plugin_form_factory,
    ChecklistTempstoreRepository $checklist_tempstore_repository
  ) {
    $this->pluginFormFactory = $plugin_form_factory;
    $this->checklistTempstoreRepo = $checklist_tempstore_repository;
  }

  /**
   * Set the checklist item
   *
   * @param \Drupal\checklist\Entity\ChecklistItemInterface $item
   */
  public function setChecklistItem(ChecklistItemInterface $item) {
    $this->item = $item;
  }

  /**
   * Get the checklist item
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface|null
   */
  public function getChecklistItem() : ?ChecklistItemInterface {
    return $this->item;
  }

  /**
   * Set the action url
   *
   * @param \Drupal\Core\Url $action_url
   *
   * @return \Drupal\checklist\Form\ChecklistItemFormBase
   */
  public function setActionUrl(Url $action_url) : ChecklistItemFormBase {
    $this->actionUrl = $action_url;
    return $this;
  }

  /**
   * Get the action url
   *
   * @return \Drupal\Core\Url|null
   */
  public function getActionUrl() : ?Url {
    return $this->actionUrl;
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $handler = $this->item->getHandler();
    if (!$handler->hasFormClass($this->formClass)) {
      throw new \Exception('Checklist item plugins MUST have a row form.');
    }

    if ($url = $this->getActionUrl()) {
      $form['#action'] = $url->toString();
    }

    /** @var \Drupal\Core\Plugin\PluginFormInterface $plugin_form */
    $plugin_form = $this->pluginFormFactory->createInstance($handler, $this->formClass);
    return $plugin_form->buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $plugin_form = $this->pluginFormFactory->createInstance($this->item->getHandler(), $this->formClass);
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
      $this->item->getHandler(), $this->formClass
    );
    $plugin_form->submitConfigurationForm($form, $form_state);

    $this->checklistTempstoreRepo->set($checklist);
  }

  /**
   * Prepare the ajax settings.
   *
   * @param array $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  protected function prepareAjaxSettings(array &$element, FormStateInterface $form_state) {
    if ($form_state->getFormObject() instanceof ChecklistItemFormBase && ($url = $form_state->getFormObject()->getActionUrl())) {
      /** @var \Drupal\Core\Url $ajax_url */
      $ajax_url = clone $url;
      $ajax_url->mergeOptions([
        'query' => [
          FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
        ]
      ]);
      $element['#ajax']['url'] = $ajax_url;
      $element['#ajax']['options'] = $ajax_url->getOptions();
    }
  }

  protected function prepareAllAjaxSettings(array &$elements, Url $url) {
    if (!empty($elements['#ajax']) && empty($elements['#ajax']['url'])) {
      $elements['#ajax']['url'] = $url;
      $elements['#ajax']['options'] = $url->getOptions();
    }

    foreach (Element::children($elements) as $child) {
      $this->prepareAllAjaxSettings($elements[$child], $url);
    }
  }

}
