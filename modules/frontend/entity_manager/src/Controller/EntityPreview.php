<?php

namespace Drupal\entity_manager\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity_manager\Plugin\views\style\EntityManager;
use Drupal\views\Views;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves preview identity and presentation only from trusted View settings.
 */
final class EntityPreview extends ControllerBase {

  /**
   * Loads the configured display and its base entity, never a supplied type.
   */
  private function resolve(string $view_id, string $display_id, string $entity_id): array {
    $view = Views::getView($view_id);
    if (!$view || !$view->storage->status() || !$view->setDisplay($display_id) || !$view->display_handler->isEnabled()) {
      throw new NotFoundHttpException();
    }
    $view->initStyle();
    if (!$view->style_plugin instanceof EntityManager) {
      throw new NotFoundHttpException();
    }
    $type = $view->getBaseEntityType();
    if (!$type || !$type->hasHandlerClass('view_builder')) {
      throw new NotFoundHttpException();
    }
    $entity = $this->entityTypeManager()->getStorage($type->id())->load($entity_id);
    if (!$entity instanceof ContentEntityInterface) {
      throw new NotFoundHttpException();
    }
    return [$view, $entity];
  }

  /**
   * Checks both View access and the current entity's access handler.
   */
  public function access(string $view_id, string $display_id, string $entity_id, AccountInterface $account) {
    [$view, $entity] = $this->resolve($view_id, $display_id, $entity_id);
    return AccessResult::allowedIf($view->access($display_id, $account))
      ->andIf($entity->access('view', $account, TRUE))->setCacheMaxAge(0);
  }

  /**
   * Renders current entity data with its normal view builder and field access.
   */
  public function preview(string $view_id, string $display_id, string $entity_id): AjaxResponse {
    if (!$this->access($view_id, $display_id, $entity_id, $this->currentUser())->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    [$view, $entity] = $this->resolve($view_id, $display_id, $entity_id);
    $content = [
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        'label' => ['#plain_text' => (string) $entity->label()],
        '#attributes' => ['tabindex' => '-1', 'data-entity-manager-heading' => ''],
      ],
      'entity' => $this->entityTypeManager()->getViewBuilder($entity->getEntityTypeId())->view($entity, $view->style_plugin->options['view_mode']),
      '#cache' => ['max-age' => 0],
    ];
    $response = new AjaxResponse();
    // NULL targets the wrapper of the initiating Drupal.ajax instance. No
    // browser-provided selector is reflected into the response.
    $response->addCommand(new InsertCommand(NULL, $content));
    $response->headers->set('Cache-Control', 'private, no-store');
    return $response;
  }

}
