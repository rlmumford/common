<?php

namespace Drupal\checklist\Controller;

use Drupal\checklist\ChecklistContextCollectorInterface;
use Drupal\checklist\ChecklistInterface;
use Drupal\checklist\Form\ChecklistItemActionForm;
use Drupal\checklist\Form\ChecklistItemRowForm;
use Drupal\checklist\PluginForm\CustomFormObjectClassInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The checklist controller.
 */
class ChecklistController extends ControllerBase {

  /**
   * The class resolver service.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected ContextHandlerInterface $contextHandler;

  /**
   * The context collector.
   *
   * @var \Drupal\checklist\ChecklistContextCollectorInterface
   */
  protected ChecklistContextCollectorInterface $contextCollector;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('class_resolver'),
      $container->get('context.handler'),
      $container->get('checklist.context_collector'),
    );
  }

  /**
   * ChecklistController constructor.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver service.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $context_handler
   *   The context handler.
   * @param \Drupal\checklist\ChecklistContextCollectorInterface $collector
   *   The context collector.
   */
  public function __construct(FormBuilderInterface $form_builder, ClassResolverInterface $class_resolver, ContextHandlerInterface $context_handler, ChecklistContextCollectorInterface $collector) {
    $this->classResolver = $class_resolver;
    $this->formBuilder = $form_builder;
    $this->contextHandler = $context_handler;
    $this->contextCollector = $collector;
  }

  /**
   * Get the action form for the checklist item.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The checklist.
   * @param string $item_name
   *   The checklist item name.
   *
   * @return array
   *   The form array.
   */
  public function actionForm(ChecklistInterface $checklist, string $item_name) {
    $item = $checklist->getItem($item_name);
    $handler = $item->getHandler();

    if ($handler instanceof ContextAwarePluginInterface) {
      $this->contextHandler->applyContextMapping(
        $handler,
        $this->contextCollector->collectRuntimeContexts($checklist)
      );
    }

    if (!$handler->hasFormClass('action')) {
      throw new NotFoundHttpException();
    }

    $form_class = ChecklistItemActionForm::class;
    if (is_subclass_of($handler->getFormClass('action'), CustomFormObjectClassInterface::class)) {
      $form_class = [$handler->getFormClass('action'), 'getFormObjectClass']($handler, $form_class);
    }

    /** @var \Drupal\checklist\Form\ChecklistItemActionForm $form_obj */
    $form_obj = $this->classResolver->getInstanceFromDefinition($form_class);
    $form_obj->setChecklistItem($item);
    return $this->formBuilder->getForm($form_obj);
  }

  /**
   * Access callback for the action form.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The checklist.
   * @param string $item_name
   *   The checklist item name.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function actionFormAccess(ChecklistInterface $checklist, string $item_name) {
    return AccessResult::allowed();
  }

  /**
   * Load the checklist item row form.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The checklist.
   * @param string $item_name
   *   The checklist item name.
   *
   * @return array
   *   The form array.
   */
  public function rowForm(ChecklistInterface $checklist, string $item_name) {
    $item = $checklist->getItem($item_name);
    $handler = $item->getHandler();

    if (!$handler->hasFormClass('row')) {
      throw new NotFoundHttpException();
    }

    $form_class = ChecklistItemRowForm::class;
    if (is_subclass_of($handler->getFormClass('row'), CustomFormObjectClassInterface::class)) {
      $form_class = [$handler->getFormClass('row'), 'getFormObjectClass']($handler, $form_class);
    }

    /** @var \Drupal\checklist\Form\ChecklistItemActionForm $form_obj */
    $form_obj = $this->classResolver->getInstanceFromDefinition($form_class);
    $form_obj->setChecklistItem($item);
    return $this->formBuilder->getForm($form_obj);
  }

  /**
   * Access callback for the row form.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The checklist.
   * @param string $item_name
   *   The checklist item name.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function rowFormAccess(ChecklistInterface $checklist, string $item_name) {
    return AccessResult::allowed();
  }

}
