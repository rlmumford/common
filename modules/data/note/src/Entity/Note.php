<?php

namespace Drupal\note\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the note entity type.
 *
 * @package Drupal\note\Entity
 *
 * @ContentEntityType(
 *   id = "note",
 *   label = @Translation("Note"),
 *   label_singular = @Translation("note"),
 *   label_plural = @Translation("notes"),
 *   label_count = @PluralTranslation(
 *     singular = "@count note",
 *     plural = "@count notes"
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\note\Entity\NoteListBuilder",
 *     "storage" = "Drupal\note\Entity\NoteStorage",
 *     "access" = "Drupal\note\Entity\NoteAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\note\Form\NoteForm",
 *       "reply" = "Drupal\note\Form\NoteReplyForm",
 *     },
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "note",
 *   revision_table = "note_revision",
 *   admin_permission = "administer notes",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "uuid" = "uuid",
 *     "label" = "subject",
 *     "owner" = "author",
 *   },
 *   links = {
 *     "canonical" = "/note/{note}",
 *     "edit-form" = "/note/{note}/edit"
 *   }
 * );
 */
class Note extends ContentEntityBase implements EntityOwnerInterface, NoteInterface {
  use EntityOwnerTrait;
  use EntityChangedTrait;

  /**
   * Get the definition of a note attachment field.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type that can be attached.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition
   *   The attachment entity reference field.
   */
  public static function attachmentBaseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields[$entity_type->id()] = BaseFieldDefinition::create('entity_reference')
      ->setLabel($entity_type->getLabel())
      ->setSetting('target_type', $entity_type->id())
      ->setSetting('note_attachment_field', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['subject'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Subject'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['content'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Content'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
      ])
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['in_reply_to'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('In Reply To'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription('The time the note was created.')
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the node was last changed.'))
      ->setRevisionable(TRUE);

    foreach (\Drupal::entityTypeManager()->getDefinitions() as $id => $entity_type) {
      if ($entity_type->get('has_notes')) {
        $fields += static::attachmentBaseFieldDefinitions($entity_type);
      }
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function createReply() {
    /** @var \Drupal\note\Entity\Note $reply */
    $reply = \Drupal::entityTypeManager()->getStorage('note')->create([]);
    $reply->in_reply_to = $this;

    foreach ($this->getFieldDefinitions() as $key => $definition) {
      /** @var \Drupal\Core\Field\FieldDefinitionInterface $definition */
      if ($definition->getSetting('note_attachment_field')) {
        $reply->{$key} = $this->{$key};
      }
    }

    return $reply;
  }

}
