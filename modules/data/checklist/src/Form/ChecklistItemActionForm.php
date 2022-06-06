<?php

namespace Drupal\checklist\Form;

use Drupal\checklist\Ajax\EnsureItemCompleteCommand;
use Drupal\checklist\Ajax\StartNextItemCommand;
use Drupal\checklist\ChecklistContextCollectorInterface;
use Drupal\checklist\ChecklistInterface;
use Drupal\checklist\ChecklistTempstoreRepository;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\PluginForm\CustomFormObjectClassInterface;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Plugin\Exception\MissingValueContextException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
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
   * The context handler service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected ContextHandlerInterface $contextHandler;

  /**
   * The context collector service.
   *
   * @var \Drupal\checklist\ChecklistContextCollectorInterface
   */
  protected ChecklistContextCollectorInterface $contextCollector;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin_form.factory'),
      $container->get('checklist.tempstore_repository'),
      $container->get('class_resolver'),
      $container->get('form_builder'),
      $container->get('renderer'),
      $container->get('context.handler'),
      $container->get('checklist.context_collector')
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
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $context_handler
   *   The context handler service.
   * @param \Drupal\checklist\ChecklistContextCollectorInterface $context_collector
   *   The context collector service.
   */
  public function __construct(
    PluginFormFactoryInterface $plugin_form_factory,
    ChecklistTempstoreRepository $checklist_tempstore_repository,
    ClassResolverInterface $class_resolver,
    FormBuilderInterface $form_builder,
    RendererInterface $renderer,
    ContextHandlerInterface $context_handler,
    ChecklistContextCollectorInterface $context_collector
  ) {
    parent::__construct($plugin_form_factory, $checklist_tempstore_repository, $context_handler);
    $this->classResolver = $class_resolver;
    $this->formBuilder = $form_builder;
    $this->renderer = $renderer;
    $this->contextCollector = $context_collector;
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

    $form['#process'][] = '::processSetAjaxActionUrls';

    return parent::buildForm($form, $form_state);
  }

  /**
   * Traverse the form/element to correct any ajax urls.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The corrected element.
   */
  public function processSetAjaxActionUrls(array $element, FormStateInterface $form_state) {
    if ($form_state->getFormObject() instanceof ChecklistItemFormBase && ($url = $form_state->getFormObject()->getActionUrl())) {
      $ajax_url = clone $url;
      $options = $ajax_url->getOptions();
      $options['query'][FormBuilderInterface::AJAX_FORM_REQUEST] = TRUE;
      $ajax_url->setOptions($options);

      $this->prepareAllAjaxSettings($element, $ajax_url);
    }

    return $element;
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
    $contexts = $this->contextCollector->collectRuntimeContexts($checklist);
    $this->addRefreshRowCommand($response, $checklist, $this->item, $contexts);

    if ($this->item->isComplete()) {
      $response->addCommand(new EnsureItemCompleteCommand($this->item));

      foreach ($checklist->getItems() as $item) {
        if ($item->isComplete() || $item->id() === $this->item->id()) {
          continue;
        }

        try {
          $this->addRefreshRowCommand($response, $checklist, $item, $contexts);
        }
        catch (ContextException $exception) {
          // Do nothing.
        }
      }

      $response->addCommand(new StartNextItemCommand($this->item));
    }

    return $response;
  }

}
