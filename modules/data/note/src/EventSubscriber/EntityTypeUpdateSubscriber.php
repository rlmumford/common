<?php

namespace Drupal\note\EventSubscriber;

use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityTypeEventSubscriberTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\note\Entity\Note;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribe to events when entity definitions change.
 */
class EntityTypeUpdateSubscriber implements EventSubscriberInterface {
  use EntityTypeEventSubscriberTrait;

  /**
   * The update manager.
   *
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   */
  protected $updateManager;

  /**
   * EntityTypeUpdateSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $update_manager
   *   The update manager.
   */
  public function __construct(EntityDefinitionUpdateManagerInterface $update_manager) {
    $this->updateManager = $update_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return static::getEntityTypeEvents();
  }

  /**
   * {@inheritdoc}
   */
  public function onEntityTypeUpdate(EntityTypeInterface $entity_type, EntityTypeInterface $original) {
    if ($entity_type->get('has_notes') && !$original->get('has_notes')) {
      $this->addNoteAttachmentField($entity_type);
    }
    elseif ($original->get('has_notes') && !$entity_type->get('has_notes')) {
      $this->removeNoteAttachmentField($original);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onEntityTypeCreate(EntityTypeInterface $entity_type) {
    if ($entity_type->get('has_notes')) {
      $this->addNoteAttachmentField($entity_type);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onEntityTypeDelete(EntityTypeInterface $entity_type) {
    if ($entity_type->get('has_notes')) {
      $this->removeNoteAttachmentField($entity_type);
    }
  }

  /**
   * Add the note attachment field.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type the note can be attached to.
   */
  protected function addNoteAttachmentField(EntityTypeInterface $entity_type) {
    $fields = Note::attachmentBaseFieldDefinitions($entity_type);

    foreach ($fields as $field_name => $definition) {
      if ($this->updateManager->getFieldStorageDefinition($field_name, 'note')) {
        continue;
      }

      $this->updateManager->installFieldStorageDefinition(
        $field_name,
        'note',
        'note',
        $definition
      );
    }
  }

  /**
   * Remove the note attachment field.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type that notes can no longer be attached to.
   */
  protected function removeNoteAttachmentField(EntityTypeInterface $entity_type) {
    $fields = Note::attachmentBaseFieldDefinitions($entity_type);

    foreach ($fields as $field_name => $definition) {
      $installed_definition = $this->updateManager->getFieldStorageDefinition(
        $field_name,
        'note'
      );
      if ($installed_definition) {
        $this->updateManager->uninstallFieldStorageDefinition($installed_definition);
      }
    }
  }

}
