<?php

namespace Drupal\checklist_api\Controller;

use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\checklist\ChecklistActionOperationDispatcher;
use Drupal\checklist\ChecklistItemReader;
use Drupal\checklist\ChecklistOperationInputException;
use Drupal\checklist\ChecklistOperationSchemaValidator;
use Drupal\checklist\ChecklistResolver;
use Drupal\checklist\Workspace\ChecklistWorkspaceAddress;
use Drupal\checklist\Workspace\ChecklistWorkspaceLease;
use Drupal\checklist\Workspace\ChecklistWorkspaceStorageInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * JSON adapter for checklist item state, ownership and action operations.
 */
class ChecklistApiController extends ControllerBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityManager,
    protected ChecklistItemReader $itemReader,
    protected ChecklistResolver $resolver,
    protected ChecklistActionOperationDispatcher $dispatcher,
    protected ChecklistOperationSchemaValidator $schemaValidator,
    protected ChecklistWorkspaceStorageInterface $workspaceStorage,
    protected AccountProxyInterface $currentUser,
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
      $container->get('checklist.operation_schema_validator'),
      $container->get('checklist.workspace_storage'),
      $container->get('current_user'),
    );
  }

  /**
   * Returns the current item state and stable checklist instance identity.
   */
  public function itemState(string $entity_type, string $entity_id, string $checklist, string $item_name): JsonResponse {
    $entity = $this->loadEntity($entity_type, $entity_id);
    [$field_name, $delta] = $this->parseChecklistAddress($checklist);
    $state = $this->itemReader->read($entity, $field_name, $delta, $item_name);
    $state['instance_uuid'] = $entity->get($field_name)->get($delta)->getPersistedInstanceUuid();
    return new JsonResponse($state);
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
      'instance_uuid' => $entity->get($field_name)->get($delta)->getPersistedInstanceUuid(),
      'operations' => $this->dispatcher->discover($checklist_object, $item_name),
    ]);
  }

  /**
   * Acquires or resumes the authenticated user's workspace lease.
   */
  public function acquireWorkspace(Request $request, string $entity_type, string $entity_id, string $checklist): JsonResponse {
    $payload = $this->decodeObject($request);
    if ($payload === NULL || (isset($payload->instance_uuid) && !is_string($payload->instance_uuid))) {
      return new JsonResponse(['error' => 'The request must contain a valid instance_uuid.'], 400);
    }

    try {
      [$entity, $field_name, $delta, $checklist_object] = $this->resolveAddress($entity_type, $entity_id, $checklist);
      $field_item = $entity->get($field_name)->get($delta);
      $persisted_uuid = $field_item->getPersistedInstanceUuid();
      $requested_uuid = $payload->instance_uuid ?? NULL;
      if ($requested_uuid !== $persisted_uuid) {
        throw new ChecklistAttemptConflictException('The checklist instance has changed.');
      }
      if ($persisted_uuid === NULL) {
        return new JsonResponse([
          'error' => 'Save and reload the host before opening a workspace for this checklist.',
        ], 409);
      }
      $address = ChecklistWorkspaceAddress::fromEntity($entity, $field_name, $delta, $checklist_object->getKey());
      $lease = $this->workspaceStorage->acquire($address, $this->ownerId());
      return new JsonResponse($this->workspaceResponse($lease), 200);
    }
    catch (ChecklistAttemptConflictException) {
      return $this->conflictResponse();
    }
  }

  /**
   * Renews the authenticated user's current workspace lease.
   */
  public function renewWorkspace(Request $request, string $entity_type, string $entity_id, string $checklist): JsonResponse {
    $payload = $this->decodeObject($request);
    if (!$this->hasLeasePayload($payload)) {
      return new JsonResponse(['error' => 'The request must contain instance_uuid and generation.'], 400);
    }

    try {
      [$entity, $field_name, $delta, $checklist_object] = $this->resolveAddress($entity_type, $entity_id, $checklist);
      $address = $this->verifiedAddress($entity, $field_name, $delta, $checklist_object->getKey(), $payload->instance_uuid);
      $lease = $this->ownedLease($address, $payload->generation);
      return new JsonResponse($this->workspaceResponse($this->workspaceStorage->renew($lease)));
    }
    catch (ChecklistAttemptConflictException) {
      return $this->conflictResponse();
    }
  }

  /**
   * Releases the authenticated user's current workspace lease.
   */
  public function releaseWorkspace(Request $request, string $entity_type, string $entity_id, string $checklist): JsonResponse {
    $payload = $this->decodeObject($request);
    if (!$this->hasLeasePayload($payload)) {
      return new JsonResponse(['error' => 'The request must contain instance_uuid and generation.'], 400);
    }

    try {
      [$entity, $field_name, $delta, $checklist_object] = $this->resolveAddress($entity_type, $entity_id, $checklist);
      $address = $this->verifiedAddress($entity, $field_name, $delta, $checklist_object->getKey(), $payload->instance_uuid);
      $lease = $this->ownedLease($address, $payload->generation);
      $this->workspaceStorage->release($lease);
      return new Response('', 204);
    }
    catch (ChecklistAttemptConflictException) {
      return $this->conflictResponse();
    }
  }

  /**
   * Executes an operation under the current owner, generation and version.
   */
  public function execute(Request $request, string $entity_type, string $entity_id, string $checklist, string $item_name): JsonResponse {
    $payload = $this->decodeObject($request);
    if (
      $payload === NULL
      || empty($payload->operation)
      || !is_string($payload->operation)
      || !isset($payload->instance_uuid)
      || !is_string($payload->instance_uuid)
      || !isset($payload->generation)
      || !is_int($payload->generation)
      || !isset($payload->expected_version)
      || !is_int($payload->expected_version)
      || $payload->expected_version < 0
    ) {
      return new JsonResponse([
        'error' => 'The request must contain operation, instance_uuid, generation and expected_version.',
      ], 400);
    }
    $parameters = $payload->parameters ?? new \stdClass();
    if (!$parameters instanceof \stdClass) {
      return new JsonResponse(['error' => 'Operation parameters must be a JSON object.'], 400);
    }

    try {
      [$entity, $field_name, $delta, $checklist_object] = $this->resolveAddress($entity_type, $entity_id, $checklist);
      $address = $this->verifiedAddress($entity, $field_name, $delta, $checklist_object->getKey(), $payload->instance_uuid);
      $lease = $this->ownedLease($address, $payload->generation);
      $operations = $this->dispatcher->discover($checklist_object, $item_name);
      if (!isset($operations[$payload->operation])) {
        throw new ChecklistAttemptConflictException('The checklist operation is no longer available.');
      }
      $this->schemaValidator->validateInput($parameters, $operations[$payload->operation]['parameters_schema']);
      $updated_lease = $this->workspaceStorage->advanceVersion($lease, $payload->expected_version);
      $result = $this->dispatcher->execute(
        $checklist_object,
        $item_name,
        $payload->operation,
        $parameters,
      );
      return new JsonResponse([
        'result' => $result,
        'workspace' => $this->workspaceResponse($updated_lease),
      ]);
    }
    catch (ChecklistOperationInputException) {
      return new JsonResponse(['error' => 'Operation parameters do not match the required schema.'], 400);
    }
    catch (ChecklistAttemptConflictException) {
      return $this->conflictResponse();
    }
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
   * Resolves an editable checklist and its current address components.
   */
  protected function resolveAddress(string $entity_type, string $entity_id, string $checklist): array {
    $entity = $this->loadEntity($entity_type, $entity_id);
    [$field_name, $delta] = $this->parseChecklistAddress($checklist);
    $checklist_object = $this->resolve($entity, $field_name, $delta, 'update');
    return [$entity, $field_name, $delta, $checklist_object];
  }

  /**
   * Confirms a caller's instance UUID matches the current field item.
   */
  protected function verifiedAddress(FieldableEntityInterface $entity, string $field_name, int $delta, string $checklist_key, string $instance_uuid): ChecklistWorkspaceAddress {
    $field_item = $entity->get($field_name)->get($delta);
    if ($field_item->getPersistedInstanceUuid() !== $instance_uuid) {
      throw new ChecklistAttemptConflictException('The checklist instance has changed.');
    }
    return ChecklistWorkspaceAddress::fromEntity($entity, $field_name, $delta, $checklist_key);
  }

  /**
   * Loads the lease and confirms its owner and generation.
   */
  protected function ownedLease(ChecklistWorkspaceAddress $address, int $generation): ChecklistWorkspaceLease {
    $owner = $this->ownerId();
    $lease = $this->workspaceStorage->load($address);
    if (!$lease || $lease->owner !== $owner || $lease->generation !== $generation || !$this->workspaceStorage->isCurrent($lease)) {
      throw new ChecklistAttemptConflictException('The checklist workspace lease has changed.');
    }
    return $lease;
  }

  /**
   * Returns the authenticated workspace owner ID.
   */
  protected function ownerId(): int {
    $owner = (int) $this->currentUser->id();
    if ($owner < 1) {
      throw new AccessDeniedHttpException('An authenticated user is required to edit this checklist.');
    }
    return $owner;
  }

  /**
   * Serializes the lease fields clients need to renew and mutate safely.
   */
  protected function workspaceResponse(ChecklistWorkspaceLease $lease): array {
    return [
      'instance_uuid' => $lease->address->instanceUuid,
      'generation' => $lease->generation,
      'version' => $lease->version,
      'expires' => $lease->expires,
    ];
  }

  /**
   * Decodes a JSON request object.
   */
  protected function decodeObject(Request $request): ?\stdClass {
    $payload = json_decode($request->getContent());
    return $payload instanceof \stdClass ? $payload : NULL;
  }

  /**
   * Checks the instance and generation fields required by lease operations.
   */
  protected function hasLeasePayload(?\stdClass $payload): bool {
    return $payload !== NULL
      && isset($payload->instance_uuid)
      && is_string($payload->instance_uuid)
      && isset($payload->generation)
      && is_int($payload->generation);
  }

  /**
   * Returns a generic conflict response without exposing another owner.
   */
  protected function conflictResponse(): JsonResponse {
    return new JsonResponse(['error' => 'The checklist workspace changed; refresh and retry.'], 409);
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
