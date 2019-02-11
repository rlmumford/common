<?php

namespace Drupal\note\EventSubscriber;

use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityTypeEventSubscriberTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\note\Entity\Note;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EntityTypeUpdateSubscriber implements EventSubscriberInterface {
  use EntityTypeEventSubscriberTrait;

  /**
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   */
  protected $updateManager;

  /**
   * EntityTypeUpdateSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $update_manager
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
    else if ($original->get('has_notes') && !$entity_type->get('has_notes')) {
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
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
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
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
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
