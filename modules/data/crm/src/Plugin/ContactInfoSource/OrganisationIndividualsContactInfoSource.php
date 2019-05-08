<?php

namespace Drupal\rlmcrm\Plugin\ContactInfoSource;

use Drupal\communication\Contact\ContactInfo;
use Drupal\communication\Contact\ContactInfoDefinitionInterface;
use Drupal\communication\Plugin\ContactInfoSource\ContactInfoSourceBase;
use Drupal\communication\Plugin\ContactInfoSource\ContactInfoSourceMetadataInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ProfileFieldSource
 *
 * @ContactInfoSource(
 *   id = "organisation_individuals",
 *   label = @Translation("Organisation Individuals"),
 *   entity_type_id = "user",
 * )
 *
 * @package Drupal\communication_user\Plugin\ContactInfoSource
 */
class OrganisationIndividualsContactInfoSource extends ContactInfoSourceBase implements ContactInfoSourceMetadataInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function collectInfo(EntityInterface $entity, ContactInfoDefinitionInterface $definition, array $options = []) {
    /** @var \Drupal\user\UserInterface $entity */
    if (!$entity->hasRole('organisation')) {
      return [];
    }

    $info = [];
    foreach ($entity->get('individuals') as $relationship_item) {
      foreach (['work_tel', 'mobile_tel', 'email_address'] as $field_name) {
        if ($definition->getDataType() == 'email' && $field_name != 'email_address') {
          continue;
        }
        else if ($definition->getDataType() == 'telephone' && $field_name == 'email_address') {
          continue;
        }

        $sub_key = "{$relationship_item->relationship->id()}.{$field_name}";
        $info[$sub_key] = new ContactInfo($entity, $this->getPluginId(), $sub_key);
        if ($relationship_item->relationship->get($field_name)->isEmpty()) {
          $info[$sub_key]->setIncomplete();
        }
      }
    }

    // @todo: New relationship

    return $info;
  }

  /**
   * @return mixed
   */
  public function getInfoValue(EntityInterface $entity, $key, $name, DataDefinitionInterface $definition) {
    list($rel_id, $field_name) = explode('.', $key, 2);

    if ($name == 'name') {
      return $entity->getDisplayName();
    }

    if (is_numeric($rel_id)) {
      /** @var \Drupal\relationships\Entity\Relationship $relationship */
      $relationship = $this->entityTypeManager->getStorage('relationship')
        ->load($rel_id);

      return $relationship->get($field_name)->value;
    }
    else {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function supportsWriteBackInfoValue(EntityInterface $entity, $key, $name, DataDefinitionInterface $definition) {
    return ($name != "name");
  }

  /**
   * {@inheritdoc}
   */
  public function writeBackInfoValues(EntityInterface $entity, $key, DataDefinitionInterface $definition, $values) {
    list($rel_id, $field_name) = explode('.', $key, 2);

    $relationship_storage = $this->entityTypeManager->getStorage('relationship');
    if ($rel_id == 'NEW') {

    }
    else {
      $relationship = $relationship_storage->load($rel_id);
      foreach ($values as $vkey => $value) {
        if (in_array($vkey, ['email', 'telephone', 'address'])) {
          $relationship->{$field_name} = $value;
        }
        else if ($vkey == 'metadata') {
          foreach ($value as $mkey => $mvalue) {
            $relationship->{$mkey} = $mvalue;
          }
        }
      }
      $relationship->save();
    }

    return $key;
  }

  /**
   * The form for setting metadata.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function metadataForm(array $form, FormStateInterface $form_state, EntityInterface $entity, $sub_key) {
    $relationship = $this->getRelationship($entity, $sub_key);
    $fields = ['role', 'role_title', 'notes'];

    foreach ($fields as $field_name) {
      $widget = $this->widgetManager->getInstance([
        'field_definition' => $relationship->getFieldDefinition($field_name),
        'form_mode' => 'default',
      ]);
      $form[$field_name] = $widget->form($relationship->get($field_name), $form, $form_state);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(EntityInterface $entity, $key) {
    list($rel_id, $field_name) = explode('.', $key, 2);

    if (is_numeric($rel_id)) {
      /** @var \Drupal\relationships\Entity\Relationship $relationship */
      $relationship = $this->entityTypeManager->getStorage('relationship')
        ->load($rel_id);

      /** @var \Drupal\Core\Entity\ContentEntityBase $individual */
      $individual = $relationship->tail->entity;
      $field_definition = $relationship->getFieldDefinition($field_name);

      $label = "{$individual->label()} {$field_definition->getLabel()}";
      if (!$relationship->get($field_name)->isEmpty()) {
        $label .= " ({$relationship->get($field_name)->value})";
      }
      return $label;
    }
    else {
      return "New Individual";
    }
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param $key
   *
   * @return \Drupal\relationships\Entity\Relationship
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getRelationship(EntityInterface $entity, $key) {
    list($rel_id, $field_name) = explode('.', $key, 2);
    if (is_numeric($rel_id)) {
      /** @var \Drupal\relationships\Entity\Relationship $relationship */
      return $this->entityTypeManager
        ->getStorage('relationship')
        ->load($rel_id);
    }
    else {
      return $this->entityTypeManager
        ->getStorage('relationship')
        ->create([
          'type' => 'individual_organisation',
          'head' => $entity,
        ]);
    }
  }
}
