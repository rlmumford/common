<?php

namespace Drupal\checklist_api\Controller;

use Drupal\checklist\ChecklistActionOperationDispatcher;
use Drupal\checklist\ChecklistItemReader;
use Drupal\checklist\ChecklistResolver;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * JSON adapter for checklist item state and action operations.
 */
class ChecklistApiController extends ControllerBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityManager,
    protected ChecklistItemReader $itemReader,
    protected ChecklistResolver $resolver,
    protected ChecklistActionOperationDispatcher $dispatcher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('checklist.item_reader'),
      $container->get('checklist.resolver'),
      $container->get('checklist.action_operation_dispatcher'),
    );
  }

  /**
   * Returns the current item state.
   */
  public function itemState(string $entity_type, string $entity_id, string $checklist, string $item_name): JsonResponse {
    $entity = $this->loadEntity($entity_type, $entity_id);
    [$field_name, $delta] = $this->parseChecklistAddress($checklist);
    return new JsonResponse($this->itemReader->read($entity, $field_name, $delta, $item_name));
  }

  /**
   * Returns operations currently available for an item.
   */
  public function operations(string $entity_type, string $entity_id, string $checklist, string $item_name): JsonResponse {
    $entity = $this->loadEntity($entity_type, $entity_id);
    [$field_name, $delta] = $this->parseChecklistAddress($checklist);
    $checklist_object = $this->resolve($entity, $field_name, $delta, 'update');
    return new JsonResponse([
      'item' => $item_name,
      'operations' => $this->dispatcher->discover($checklist_object, $item_name),
    ]);
  }

  /**
   * Executes one advertised operation.
   */
  public function execute(Request $request, string $entity_type, string $entity_id, string $checklist, string $item_name): JsonResponse {
    $entity = $this->loadEntity($entity_type, $entity_id);
    [$field_name, $delta] = $this->parseChecklistAddress($checklist);
    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload) || empty($payload['operation'])) {
      return new JsonResponse(['error' => 'The request must contain an operation.'], 400);
    }
    $checklist_object = $this->resolve($entity, $field_name, $delta, 'update');
    return new JsonResponse($this->dispatcher->execute(
      $checklist_object,
      $item_name,
      (string) $payload['operation'],
      is_array($payload['parameters'] ?? NULL) ? $payload['parameters'] : [],
    ));
  }

  /**
   * Loads a fieldable host entity.
   */
  protected function loadEntity(string $entity_type, string $entity_id): FieldableEntityInterface {
    if (!$this->entityManager->hasDefinition($entity_type)) {
      throw new NotFoundHttpException('Checklist host not found.');
    }
    $entity = $this->entityManager->getStorage($entity_type)->load($entity_id);
    if (!$entity instanceof FieldableEntityInterface) {
      throw new NotFoundHttpException('Checklist host not found.');
    }
    return $entity;
  }

  /**
   * Resolves the checklist through the shared access-aware resolver.
   */
  protected function resolve(FieldableEntityInterface $entity, string $field_name, int $delta, string $operation) {
    return $this->resolver->resolve($entity, $field_name, $delta, $operation);
  }

  /**
   * Splits the public field[:delta] checklist address.
   */
  protected function parseChecklistAddress(string $address): array {
    $parts = explode(':', $address, 2);
    $delta = isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : 0;
    return [$parts[0], $delta];
  }

}
