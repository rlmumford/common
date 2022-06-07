<?php

namespace Drupal\checklist\Form;

use Drupal\checklist\ChecklistInterface;
use Drupal\checklist\ChecklistTempstoreRepository;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\PluginForm\CustomFormObjectClassInterface;
use Drupal\Component\Plugin\Exception\MissingValueContextException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Form\BaseFormIdInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base form class for checklist item forms.
 */
abstract class ChecklistItemFormBase extends FormBase implements BaseFormIdInterface {

  /**
   * What sort of form class to use.
   *
   * @var string
   */
  protected $formClass = NULL;

  /**
   * The checklist item.
   *
   * @var \Drupal\checklist\Entity\ChecklistItemInterface
   */
  protected $item;

  /**
   * The form action url.
   *
   * @var \Drupal\Core\Url
   */
  protected $actionUrl = NULL;

  /**
   * The plugin form factory.
   *
   * @var \Drupal\Core\Plugin\PluginFormFactoryInterface
   */
  protected $pluginFormFactory;

  /**
   * The tempstore repo.
   *
   * @var \Drupal\checklist\ChecklistTempstoreRepository
   */
  protected $checklistTempstoreRepo;

  /**
   * The context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected ContextHandlerInterface $contextHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin_form.factory'),
      $container->get('checklist.tempstore_repository'),
      $container->get('context.handler')
    );
  }

  /**
   * ChecklistItemFormBase constructor.
   *
   * @param \Drupal\Core\Plugin\PluginFormFactoryInterface $plugin_form_factory
   *   The plugin form factory.
   * @param \Drupal\checklist\ChecklistTempstoreRepository $checklist_tempstore_repository
   *   The tempstore repository.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $context_handler
   *   The context handler service.
   */
  public function __construct(
    PluginFormFactoryInterface $plugin_form_factory,
    ChecklistTempstoreRepository $checklist_tempstore_repository,
    ContextHandlerInterface $context_handler
  ) {
    $this->pluginFormFactory = $plugin_form_factory;
    $this->checklistTempstoreRepo = $checklist_tempstore_repository;
    $this->contextHandler = $context_handler;
  }

  /**
   * Set the checklist item.
   *
   * @param \Drupal\checklist\Entity\ChecklistItemInterface $item
   *   The checklist item.
   */
  public function setChecklistItem(ChecklistItemInterface $item) {
    $this->item = $item;
  }

  /**
   * Get the checklist item.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface|null
   *   The checklist item if it exists.
   */
  public function getChecklistItem() : ?ChecklistItemInterface {
    return $this->item;
  }

  /**
   * Set the action url.
   *
   * @param \Drupal\Core\Url $action_url
   *   The action url.
   *
   * @return $this
   */
  public function setActionUrl(Url $action_url) : ChecklistItemFormBase {
    $this->actionUrl = $action_url;
    return $this;
  }

  /**
   * Get the action url.
   *
   * @return \Drupal\Core\Url|null
   *   The form action url.
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
      throw new \Exception('Checklist item plugins MUST have a ' . $this->formClass . ' form.');
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
   * This sets the right form action url on any ajax commands.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function prepareAjaxSettings(array &$element, FormStateInterface $form_state) {
    if ($form_state->getFormObject() instanceof ChecklistItemFormBase && ($url = $form_state->getFormObject()->getActionUrl())) {
      /** @var \Drupal\Core\Url $ajax_url */
      $ajax_url = clone $url;
      $options = $ajax_url->getOptions();
      $options['query'][FormBuilderInterface::AJAX_FORM_REQUEST] = TRUE;
      $ajax_url->setOptions($options);

      $element['#ajax']['url'] = $ajax_url;
      $element['#ajax']['options'] = NestedArray::mergeDeep(
        $element['#ajax']['options'] ?? [],
        $ajax_url->getOptions()
      );
    }
  }

  /**
   * Prepare all the ajax settings.
   *
   * @param array $elements
   *   The form elements.
   * @param \Drupal\Core\Url $url
   *   The ajax url.
   */
  protected function prepareAllAjaxSettings(array &$elements, Url $url) {
    if (!empty($elements['#ajax']) && empty($elements['#ajax']['url'])) {
      $elements['#ajax']['url'] = $url;
      $elements['#ajax']['options'] = NestedArray::mergeDeep(
        $elements['#ajax']['options'] ?? [],
        $url->getOptions()
      );
    }

    if (isset($elements['#type']) && $elements['#type'] === 'managed_file') {
      $elements['#process'][] = '::prepareManagedFileAjaxSettings';
      $elements['#ajax_url'] = $url;
    }

    foreach (Element::children($elements) as $child) {
      $this->prepareAllAjaxSettings($elements[$child], $url);
    }
  }

  /**
   * Set the ajax url on file elements.
   *
   * @param array $element
   *   The managed file element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated element.
   */
  public function prepareManagedFileAjaxSettings(array $element, FormStateInterface $form_state) {
    if (!empty($element['#ajax_url'])) {
      $this->prepareAllAjaxSettings($element, $element['#ajax_url']);
      unset($element['#ajax_url']);
    }
    return $element;
  }

  /**
   * Add ajax commands to refresh the rows of future items.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The ajax response to add commands to.
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The checklist.
   * @param \Drupal\checklist\Entity\ChecklistItemInterface $item
   *   The checklist item to refresh.
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   The contexts.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   */
  protected function addRefreshRowCommand(AjaxResponse $response, ChecklistInterface $checklist, ChecklistItemInterface $item, array $contexts = []) {
    $handler = $item->getHandler();
    if ($handler instanceof ContextAwarePluginInterface) {
      try {
        $this->contextHandler->applyContextMapping($handler, $contexts);
      }
      catch (MissingValueContextException $e) {
        // Do nothing on missing value exceptions.
      }
    }

    $form_class = ChecklistItemRowForm::class;
    if (is_subclass_of($handler->getFormClass('row'), CustomFormObjectClassInterface::class)) {
      $form_class = [$handler->getFormClass('row'), 'getFormObjectClass']($handler, $form_class);
    }

    /** @var \Drupal\checklist\Form\ChecklistItemRowForm $form_obj */
    $row_form_obj = $this->classResolver->getInstanceFromDefinition($form_class);
    $row_form_obj->setChecklistItem($item);
    $row_form_obj->setActionUrl(Url::fromRoute(
      'checklist.item.row_form',
      [
        'entity_type' => $checklist->getEntity()->getEntityTypeId(),
        'entity_id' => $checklist->getEntity()->id(),
        'checklist' => $checklist->getKey(),
        'item_name' => $item->getName(),
      ]
    ));
    $row_form = $this->formBuilder->getForm($row_form_obj);
    $row_form_html = $this->renderer->renderRoot($row_form);
    $response->addAttachments($row_form['#attached']);
    $response->addCommand(new InsertCommand('#' . $row_form['#wrapper_id'], $row_form_html));
  }

}
