<?php

namespace Drupal\checklist;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Symfony\Component\Routing\Route;

/**
 * Param converter to get the checklist on some routes.
 */
class ChecklistTempstoreParamConverter implements ParamConverterInterface {

  /**
   * The checklist tempstore repository.
   *
   * @var \Drupal\checklist\ChecklistTempstoreRepository
   */
  protected $checklistTempstoreRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ChecklistTempstoreParamConverter constructor.
   *
   * @param \Drupal\checklist\ChecklistTempstoreRepository $checklist_tempstore_repository
   *   The checklist tempstore repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    ChecklistTempstoreRepository $checklist_tempstore_repository,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->checklistTempstoreRepository = $checklist_tempstore_repository;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $entity_type = $defaults['entity_type'];
    $entity_id = $defaults['entity_id'];
    $checklist = $defaults['checklist'];

    if (empty($entity_type) || empty($entity_id) || empty($checklist) || !$this->entityTypeManager->hasDefinition($entity_type)) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage($entity_type);
    $entity = $storage->load($entity_id);
    if (!$entity) {
      return NULL;
    }

    if (!strpos($checklist, ':')) {
      $field_name = $checklist;
      $delta = 0;
    }
    else {
      [$field_name, $delta] = explode(':', $checklist);
    }

    if (!($entity instanceof FieldableEntityInterface) || !$entity->hasField($field_name)) {
      return NULL;
    }

    $checklist = $entity->get($field_name)->get($delta)->checklist;
    if (!$checklist) {
      return NULL;
    }

    return $this->checklistTempstoreRepository->get($checklist);
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return !empty($definition['checklist_tempstore']);
  }

}
