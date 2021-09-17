<?php

namespace Drupal\checklist\Controller;

use Drupal\checklist\ChecklistInterface;
use Drupal\checklist\Form\ChecklistItemActionForm;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormBuilderInterface;
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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('class_resolver')
    );
  }

  /**
   * ChecklistController constructor.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver service.
   */
  public function __construct(FormBuilderInterface $form_builder, ClassResolverInterface $class_resolver) {
    $this->classResolver = $class_resolver;
    $this->formBuilder = $form_builder;
  }

  /**
   * Get the action for for the checklist item.
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

    if (!$handler->hasFormClass('action')) {
      throw new NotFoundHttpException();
    }

    /** @var \Drupal\checklist\Form\ChecklistItemActionForm $form_obj */
    $form_obj = $this->classResolver->getInstanceFromDefinition(ChecklistItemActionForm::class);
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

    /** @var \Drupal\checklist\Form\ChecklistItemActionForm $form_obj */
    $form_obj = $this->classResolver->getInstanceFromDefinition(ChecklistItemActionForm::class);
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
