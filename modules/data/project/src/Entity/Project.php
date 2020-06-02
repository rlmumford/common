<?php

namespace Drupal\project\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Entity class for Projects.
 *
 * @ContentEntityType(
 *   id = "project",
 *   label = @Translation("Project"),
 *   label_singular = @Translation("project"),
 *   label_plural = @Translation("projects"),
 *   label_count = @PluralTranslation(
 *     singular = "@count project",
 *     plural = "@count projects"
 *   ),
 *   bundle_label = @Translation("Project Type"),
 *   handlers = {
 *     "list_builder" = "Drupal\project\Entity\ProjectListBuilder",
 *     "storage" = "Drupal\project\Entity\ProjectStorage",
 *     "access" = "Drupal\project\Entity\ProjectAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\project\Form\ProjectForm"
 *     },
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "project",
 *   revision_table = "project_revision",
 *   admin_permission = "administer projects",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *     "owner" = "manager"
 *   },
 *   has_notes = "true",
 *   bundle_entity_type = "project_type",
 *   field_ui_base_route = "entity.project_type.edit_form",
 *   links = {
 *     "collection" = "/project",
 *     "canonical" = "/project/{project}",
 *     "edit-form" = "/project/{project}/edit",
 *     "add-page" = "/project/add",
 *     "add-form" = "/project/add/{project_type}"
 *   }
 * )
 */
class Project extends ContentEntityBase implements ProjectInterface, EntityOwnerInterface {
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRevisionable(TRUE)
      ->setDefaultValueCallback('\Drupal\project\Entity\Project::createLabel')
      ->setDisplayConfigurable('view', TRUE);

    $fields['state'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Active?'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['creator'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Creator'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback('\Drupal\project\Entity\Project::getCurrentUserId')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'timestamp',
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['project'] = BaseFieldDefinition::create('project_reference')
      ->setLabel(t('Project'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'project')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return ProjectType::load($this->bundle());
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    $type = $this->getType();
    if ($type->get('default_label')) {
      $this->label = $this->applyPlaceholders($type->get('default_label'));
    }
  }

  /**
   * Create the label.
   */
  public static function createLabel(ProjectInterface $project, FieldDefinitionInterface $fieldDefinition) {
    /** @var \Drupal\typed_data\PlaceholderResolverInterface $placeholder_resolver */
    $placeholder_resolver = \Drupal::service('typed_data.placeholder_resolver');

    $type = $project->getType();
    if ($type->get('default_label')) {
      return $placeholder_resolver->replacePlaceHolders(
        $type->get('default_label'),
        ['project' => $project->getTypedData()],
        new BubbleableMetadata(),
        ['clear' => TRUE]
      );
    }

    return '';
  }

  /**
   * Apply tokens to a string.
   */
  protected function applyPlaceholders($string, BubbleableMetadata $bubbleable_metadata = NULL) {
    /** @var \Drupal\typed_data\PlaceholderResolverInterface $placeholder_resolver */
    $placeholder_resolver = \Drupal::service('typed_data.placeholder_resolver');
    return $placeholder_resolver->replacePlaceHolders(
      $string,
      ['project' => $this->getTypedData()],
      $bubbleable_metadata,
      ['clear' => TRUE]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getManager() {
    return $this->manager->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getManagerId() {
    return $this->manager->target_id;
  }

  /**
   * Default value callback for author.
   */
  public static function getCurrentUserId() {
    return [\Drupal::currentUser()->id()];
  }
}

