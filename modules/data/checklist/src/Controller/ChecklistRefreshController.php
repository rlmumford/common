<?php

namespace Drupal\checklist\Controller;

use Drupal\checklist\ChecklistResolver;
use Drupal\checklist\ChecklistRowUpdater;
use Drupal\checklist\ChecklistTempstoreRepository;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Reads current checklist rows without executing or claiming work.
 */
class ChecklistRefreshController implements ContainerInjectionInterface {

  /**
   * Constructs the read-only HTML refresh controller.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ChecklistResolver $resolver,
    protected ChecklistTempstoreRepository $tempstore,
    protected ChecklistRowUpdater $rows,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('checklist.resolver'),
      $container->get('checklist.tempstore_repository'),
      $container->get('checklist.row_updater'),
    );
  }

  /**
   * Refreshes a saved checklist's current visible rows.
   */
  public function refresh(string $entity_type, string $entity_id, string $checklist, string $instance_uuid): AjaxResponse {
    if (!$this->entityTypeManager->hasDefinition($entity_type) || !preg_match('/^([^:]+)(?::(0|[1-9][0-9]*))?$/D', $checklist, $parts)) {
      throw new NotFoundHttpException();
    }
    $host = $this->entityTypeManager->getStorage($entity_type)->loadUnchanged($entity_id);
    if (!$host instanceof FieldableEntityInterface) {
      throw new NotFoundHttpException();
    }
    $field = $parts[1];
    $delta = (int) ($parts[2] ?? 0);
    // Authorize the fresh host and field before reading tempstore data.
    $current = $this->resolver->resolve($host, $field, $delta);
    if ($host->get($field)->get($delta)->getPersistedInstanceUuid() !== $instance_uuid) {
      throw new ConflictHttpException('The checklist instance has changed.');
    }
    // Keep unsaved edits; the repository reloads authoritative iterative items.
    $current = $this->tempstore->get($current);
    if ($current->getEntity()->get($field)->get($delta)->getPersistedInstanceUuid() !== $instance_uuid) {
      throw new ConflictHttpException('The working checklist instance has changed.');
    }
    $response = new AjaxResponse();
    $response->headers->set('Cache-Control', 'private, no-store');
    $this->rows->refresh($response, $current);
    return $response;
  }

}
