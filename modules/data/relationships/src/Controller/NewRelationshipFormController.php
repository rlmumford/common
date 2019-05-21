<?php

namespace Drupal\relationships\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\relationships\Entity\Relationship;
use Drupal\relationships\Entity\RelationshipType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;

class NewRelationshipFormController extends ControllerBase {

  /**
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * @var \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface
   */
  protected $argumentResolver;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
      $container->get('http_kernel.controller.argument_resolver')
    );
  }

  /**
   * NewContactFormController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   * @param \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface $argument_resolver
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    FormBuilderInterface $form_builder,
    ArgumentResolverInterface $argument_resolver
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
    $this->argumentResolver = $argument_resolver;
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return array
   * @throws \Drupal\Core\Form\EnforcedResponseException
   * @throws \Drupal\Core\Form\FormAjaxException
   */
  public function getContentResult(Request $request, RelationshipType $relationship_type, $tail_id = NULL, $head_id = NULL) {
    $form_object = $this->entityTypeManager->getFormObject(
      'relationship',
      $request->get('form_mode') ?: 'default'
    );

    // Allow the entity form to determine the entity object from a given route
    // match.
    $values = [
      'type' => $relationship_type->id(),
      'tail' => $tail_id,
      'head' => $head_id,
    ];
    $entity = $this->entityTypeManager->getStorage('relationship')
      ->create($values);
    $form_object->setEntity($entity);

    // Add the form and form_state to trick the getArguments method of the
    // controller resolver.
    $form_state = new FormState();
    $request->attributes->set('form', []);
    $request->attributes->set('form_state', $form_state);
    $args = $this->argumentResolver->getArguments($request, [$form_object, 'buildForm']);
    $request->attributes->remove('form');
    $request->attributes->remove('form_state');

    // Remove $form and $form_state from the arguments, and re-index them.
    unset($args[0], $args[1]);
    $form_state->addBuildInfo('args', array_values($args));

    return $this->formBuilder->buildForm($form_object, $form_state);
  }

}
