<?php

namespace Drupal\checklist\Form;

use Drupal\checklist\Ajax\EnsureItemCompleteCommand;
use Drupal\checklist\Ajax\StartNextItemCommand;
use Drupal\checklist\ChecklistTempstoreRepository;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Action form for checklist items.
 */
class ChecklistItemActionForm extends ChecklistItemFormBase {

  /**
   * {@inheritdoc}
   */
  protected $formClass = 'action';

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The renderer.
   *
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
   * ChecklistItemActionForm constructor.
   *
   * @param \Drupal\Core\Plugin\PluginFormFactoryInterface $plugin_form_factory
   *   The plugin form factory.
   * @param \Drupal\checklist\ChecklistTempstoreRepository $checklist_tempstore_repository
   *   The tempstore repository.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(
    PluginFormFactoryInterface $plugin_form_factory,
    ChecklistTempstoreRepository $checklist_tempstore_repository,
    ClassResolverInterface $class_resolver,
    FormBuilderInterface $form_builder,
    RendererInterface $renderer
  ) {
    parent::__construct($plugin_form_factory, $checklist_tempstore_repository);
    $this->classResolver = $class_resolver;
    $this->formBuilder = $form_builder;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ci_' . $this->item->checklist->checklist->getKey() . '__' . $this->item->getName() . '_action_form';
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
      '#value' => new TranslatableMarkup('Complete @name', ['@name' => $this->item->getName()]),
      '#name' => 'action_form_complete',
      '#submit' => [
        '::submitForm',
      ],
      '#ajax' => [
        'callback' => '::onCompleteAjaxCallback',
        'wrapper' => $wrapper_id,
      ],
    ];
    $this->prepareAjaxSettings($form['actions']['complete'], $form_state);

    return parent::buildForm($form, $form_state);
  }

  /**
   * Ajax callback on completion.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|array
   *   The form array or an ajax response.
   */
  public function onCompleteAjaxCallback(array $form, FormStateInterface $form_state) {
    if (!$form_state->isExecuted() || $form_state->isRebuilding()) {
      return $form;
    }

    $response = new AjaxResponse();

    // First, clear the action form.
    /** @var \Drupal\checklist\ChecklistInterface $checklist */
    $checklist = $this->item->checklist->checklist;
    $form_container_id = $checklist->getEntity()->getEntityTypeId()
      . '--' . str_replace(':', '--', $checklist->getKey())
      . '--' . $this->item->getName()
      . '--action-form-container';
    $response->addCommand(new InsertCommand('#' . $form_container_id, '<div id="' . $form_container_id . '"></div>'));

    // Second, refresh the row form.
    /** @var \Drupal\checklist\Form\ChecklistItemRowForm $form_obj */
    $row_form_obj = $this->classResolver->getInstanceFromDefinition(ChecklistItemRowForm::class);
    $row_form_obj->setChecklistItem($this->item);
    $row_form_obj->setActionUrl(Url::fromRoute(
      'checklist.item.row_form',
      [
        'entity_type' => $checklist->getEntity()->getEntityTypeId(),
        'entity_id' => $checklist->getEntity()->id(),
        'checklist' => $checklist->getKey(),
        'item_name' => $this->item->getName(),
      ]
    ));
    $row_form = $this->formBuilder->getForm($row_form_obj);
    $row_form_html = $this->renderer->renderRoot($row_form);
    $response->addAttachments($row_form['#attached']);
    $response->addCommand(new InsertCommand('#' . $row_form['#wrapper_id'], $row_form_html));

    if ($this->item->isComplete()) {
      $response->addCommand(new EnsureItemCompleteCommand($this->item));
      // @todo Make actionable.
      $response->addCommand(new StartNextItemCommand($this->item));
    }

    return $response;
  }

}
