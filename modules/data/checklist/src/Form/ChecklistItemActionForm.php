<?php

namespace Drupal\checklist\Form;

use Drupal\checklist\ChecklistTempstoreRepository;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ChecklistItemActionForm extends ChecklistItemFormBase {

  /**
   * @var string
   */
  protected $formClass = 'action';

  /**
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

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
      $container->get('class_resolver'),
      $container->get('form_builder'),
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
    ClassResolverInterface $class_resolver,
    FormBuilderInterface $form_builder,
    RendererInterface $renderer
  ) {
    $this->pluginFormFactory = $plugin_form_factory;
    $this->checklistTempstoreRepo = $checklist_tempstore_repository;
    $this->classResolver = $class_resolver;
    $this->formBuilder = $form_builder;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ci_'.$this->item->checklist->checklist->getKey().'__'.$this->item->getName().'_action_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    return 'ci_action_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $wrapper_id = "checklist-action--" . $this->item->checklist->checklist->getKey() . "--" . $this->item->getName();
    $form['#prefix'] = '<div id="' . $wrapper_id . '" class="checklist-item-action-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['complete'] = [
      '#type' => 'submit',
      '#value' => new TranslatableMarkup('Complete'),
      '#name' => 'action_form_complete',
      '#submit' => [
        '::submitForm'
      ],
      '#ajax' => [
        'callback' => '::onCompleteAjaxCallback',
        'wrapper' => $wrapper_id,
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  public function onCompleteAjaxCallback($form, FormStateInterface $form_state) {
    if (!$form_state->isExecuted() || $form_state->isRebuilding()) {
      return $form;
    }

    $response = new AjaxResponse();

    // First, clear the action form.
    /** @var \Drupal\checklist\ChecklistInterface $checklist */
    $checklist = $this->item->checklist->checklist;
    $form_container_id = $checklist->getEntity()->getEntityTypeId()
      .'--'.str_replace(':', '--', $checklist->getKey())
      .'--action-form-container';
    $response->addCommand(new InsertCommand('#'.$form_container_id,'<div id="'.$form_container_id.'"></div>'));

    // Second, refresh the row form.
    // @todo: We need to find a way of keeping the action of this form consistent.
    /** @var \Drupal\checklist\Form\ChecklistRowForm $form_obj */
    $form_obj = $this->classResolver->getInstanceFromDefinition(ChecklistRowForm::class);
    $form_obj->setChecklistItem($this->item);
    $row_form = $this->formBuilder->getForm($form_obj);
    $row_form_html = $this->renderer->renderRoot($row_form);
    $response->addAttachments($row_form['#attached']);
    $response->addCommand(new InsertCommand('#'.$row_form['#wrapper_id'], $row_form_html));

    // @todo: Update other items.

    return $response;
  }
}
