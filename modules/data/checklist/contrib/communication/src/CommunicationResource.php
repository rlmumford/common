<?php

namespace Drupal\checklist_communication;

use Drupal\checklist\ChecklistActionResource;
use Drupal\communication\Entity\CommunicationInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Renders saved communications for review without invoking their operations.
 */
class CommunicationResource {

  use StringTranslationTrait;

  public function __construct(protected EntityTypeManagerInterface $entityTypes) {}

  /**
   * Builds a shared pane from the current, access-checked saved communication.
   */
  public function build(?CommunicationInterface $communication): ?ChecklistActionResource {
    if (!$communication || $communication->isNew()) {
      return NULL;
    }
    // Outcomes or retained contexts can predate a worker's status update.
    $communication = $this->entityTypes->getStorage('communication')->loadUnchanged($communication->id());
    if (!$communication) {
      return NULL;
    }
    $access = $communication->access('view', NULL, TRUE);
    if (!$access->isAllowed()) {
      return NULL;
    }
    $subject = $communication->get('subject');
    $subject_access = $subject->access('view', NULL, TRUE);
    $content = [
      '#type' => 'container',
      '#attributes' => ['class' => ['checklist-communication-review']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#access' => $subject_access,
        'text' => ['#plain_text' => $subject_access->isAllowed() ? $communication->label() : ''],
      ],
      'message' => $this->entityTypes->getViewBuilder('communication')->view($communication, 'default'),
    ];
    CacheableMetadata::createFromObject($communication)
      ->addCacheableDependency($access)
      ->addCacheableDependency($subject_access)
      ->applyTo($content);
    return new ChecklistActionResource(
      'communication:' . $communication->uuid(),
      $content,
      (string) $this->t('Communication'),
      closeable: FALSE,
      pinned: TRUE,
    );
  }

}
