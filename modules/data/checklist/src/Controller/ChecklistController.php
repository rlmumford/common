<?php

namespace Drupal\checklist\Controller;

use Drupal\checklist\ChecklistInterface;
use Drupal\checklist\Form\ChecklistItemActionForm;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ChecklistController extends ControllerBase {

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('class_resolver')
    );
  }

  public function __construct(FormBuilderInterface $form_builder, ClassResolverInterface $class_resolver) {
    $this->classResolver = $class_resolver;
    $this->formBuilder = $form_builder;
  }

  /**
   * Get the action for for the checklist item.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   * @param string $item_name
   */
  public function actionForm(ChecklistInterface $checklist, string $item_name) {
    $item = $checklist->getItem($item_name);
    $handler = $item->getHandler();

    if (!$handler->hasFormClass('action')) {
      throw new NotFoundHttpException();
    }

    /** @var \Drupal\checklist\Form\ChecklistItemActionForm $form_obj */
    $form_obj = \Drupal::classResolver()->getInstanceFromDefinition(ChecklistItemActionForm::class);
    $form_obj->setChecklistItem($item);

    return $this->formBuilder->getForm($form_obj);
  }

}
