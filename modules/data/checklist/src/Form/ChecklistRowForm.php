<?php

namespace Drupal\checklist\Form;

use Drupal\checklist\Ajax\EnsureItemCompleteCommand;
use Drupal\checklist\Ajax\EnsureItemInProgressCommand;
use Drupal\checklist\Ajax\StartNextItemCommand;
use Drupal\checklist\ChecklistTempstoreRepository;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ChecklistRowForm extends ChecklistItemFormBase {

  /**
   * @var string
   */
  protected $formClass = 'row';

  /**
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin_form.factory'),
      $container->get('checklist.tempstore_repository'),
      $container->get('form_builder'),
      $container->get('class_resolver'),
      $container->get('renderer')
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
    ChecklistTempstoreRepository $checklist_tempstore_repository,
    FormBuilderInterface $form_builder,
    ClassResolverInterface $class_resolver,
    RendererInterface $renderer
  ) {
    $this->pluginFormFactory = $plugin_form_factory;
    $this->checklistTempstoreRepo = $checklist_tempstore_repository;
    $this->formBuilder = $form_builder;
    $this->classResolver = $class_resolver;
    $this->renderer = $renderer;
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

  public function buildForm(array $form, FormStateInterface $form_state) {
    $wrapper_id = "checklist-row--".$this->item->checklist->checklist->getKey()."--".$this->item->getName();
    $form['#prefix'] = '<div id="'.$wrapper_id.'" class="checklist-item-row-form-wrapper">';
    $form['#suffix'] = '</div>';
    $form['#wrapper_id'] = $wrapper_id;

    $form = parent::buildForm($form, $form_state);

    if ($url = $this->getActionUrl()) {
      $form['#action'] = $url->toString();
      $url->mergeOptions([
        'query' => [
          FormBuilderInterface::AJAX_FORM_REQUEST,
        ]
      ]);
      $this->prepareAllAjaxSettings($form, $url);
    }

    return $form;
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array|\Drupal\Core\Ajax\AjaxResponse
   */
  public function onCompleteAjaxCallback(array &$form, FormStateInterface $form_state) {
    if (!$form_state->isExecuted()) {
      return $form;
    }

    $response = static::prepareAjaxResponse($form, $form_state);

    // @todo: Reload any dependent forms.
    // @todo: Close any resource or form panes.
    $response->addCommand(new EnsureItemCompleteCommand($this->item));
    $response->addCommand(new StartNextItemCommand($this->item));

    return $response;
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array|\Drupal\Core\Ajax\AjaxResponse
   */
  public static function onReverseAjaxCallback(array &$form, FormStateInterface $form_state) {
    if (!$form_state->isExecuted()) {
      return $form;
    }

    $response = static::prepareAjaxResponse($form, $form_state);

    // @todo: Reload any dependent forms.
    // @todo: Close any resource or form panes.

    return $response;
  }

  public function onStartAjaxCallback(array &$form, FormStateInterface $form_state) {
    if (!$form_state->isExecuted()) {
      return $form;
    }

    $response = static::prepareAjaxResponse($form, $form_state);
    $this->insertActionForm($response);
    $response->addCommand(new EnsureItemInProgressCommand($this->item));

    return $response;
  }

  protected function insertActionForm(AjaxResponse $response, $selector = NULL) {
    /** @var \Drupal\checklist\ChecklistInterface $checklist */
    $checklist = $this->item->checklist->checklist;

    $selector = $selector ?: '#'.$checklist->getEntity()->getEntityTypeId()
      .'--'.str_replace(':', '--', $checklist->getKey())
      .'--action-form-container';

    /** @var \Drupal\checklist\Form\ChecklistItemActionForm $form_object */
    $form_object = $this->classResolver->getInstanceFromDefinition(ChecklistItemActionForm::class);
    $form_object->setChecklistItem($this->item);
    $form_object->setActionUrl(Url::fromRoute(
      'checklist.item.action_form',
      [
        'entity_type' => $checklist->getEntity()->getEntityTypeId(),
        'entity_id' => $checklist->getEntity()->id(),
        'checklist' => $checklist->getKey(),
        'item_name' => $this->item->getName(),
      ]
    ));

    $form = $this->formBuilder->getForm($form_object);
    $form_html = $this->renderer->renderRoot($form);
    $response->addAttachments($form['#attached']);
    $response->addCommand(new HtmlCommand($selector, $form_html));
  }

  protected static function prepareAjaxResponse(array &$form, FormStateInterface $form_state) : AjaxResponse {
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

    return $response;
  }


}
