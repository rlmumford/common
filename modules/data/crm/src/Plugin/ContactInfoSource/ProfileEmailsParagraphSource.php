<?php

namespace Drupal\rlmcrm\Plugin\ContactInfoSource;

use Drupal\communication\Contact\ContactInfo;
use Drupal\communication\Contact\ContactInfoDefinition;
use Drupal\communication\Contact\ContactInfoDefinitionInterface;
use Drupal\communication\Plugin\ContactInfoSource\ContactInfoSourceBase;
use Drupal\communication\Plugin\ContactInfoSource\ContactInfoSourceMetadataInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ProfileFieldSource
 *
 * @ContactInfoSource(
 *   id = "profile_contact_para",
 *   label = @Translation("Profile Contact Paragraph"),
 *   entity_type_id = "user",
 * )
 *
 * @package Drupal\communication_user\Plugin\ContactInfoSource
 */
class ProfileEmailsParagraphSource extends ContactInfoSourceBase implements ContactInfoSourceMetadataInterface {

  /**
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * @var \Drupal\Core\Field\WidgetPluginManager
   */
  protected $widgetManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.widget')
    );
  }

  /**
   * ProfileFieldSource constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   * @param \Drupal\Core\Field\WidgetPluginManager $widget_manager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, WidgetPluginManager $widget_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);

    $this->entityFieldManager = $entity_field_manager;
    $this->widgetManager = $widget_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function collectInfo(EntityInterface $entity, ContactInfoDefinitionInterface $definition, array $options = []) {
    $profile_storage = $this->entityTypeManager->getStorage('profile');
    $ids = $profile_storage->getQuery()
      ->condition('uid', $entity->id())
      ->condition('is_default', 1)
      ->execute();

    $info = [];
    /** @var \Drupal\profile\Entity\Profile $profile */
    foreach ($profile_storage->loadMultiple($ids) as $profile) {
      $bundle = $profile->bundle();
      foreach (['email_addresses' => 'email_address', 'telephone_numbers' => 'number'] as $field_name => $prop_name) {
        if ($definition->getDataType() == 'email' && $field_name != 'email_addresses') {
          continue;
        }

        if ($definition->getDataType() == 'telephone' && $field_name != 'telephone_numbers') {
          continue;
        }

        if (!in_array($definition->getDataType(), ['telephone', 'email'])) {
          continue;
        }

        if (!$profile->hasField($field_name)) {
          continue;
        }

        $items = $profile->get($field_name);

        if (!$items || !($items instanceof FieldItemListInterface)) {
          continue;
        }

        foreach ($items as $delta => $item) {
          $sub_key = "{$bundle}.{$field_name}.{$delta}";
          $info[$sub_key] = new ContactInfo($definition, $entity, $this->getPluginId(), $sub_key);
          if ($item->entity->get($prop_name)->isEmpty()) {
            $info[$sub_key]->setIncomplete();
          }
        }

        $sub_key = "{$bundle}.{$field_name}.NEW";
        $info[$sub_key] = new ContactInfo($definition, $entity, $this->getPluginId(), $sub_key);
        $info[$sub_key]->setIncomplete();
      }
    }

    return $info;
  }

  /**
   * @return mixed
   */
  public function getInfoValue(EntityInterface $entity, $key, $name, DataDefinitionInterface $definition) {
    list($bundle, $field_name, $delta) = explode('.', $key, 3);

    /** @var \Drupal\profile\ProfileStorageInterface $profile_storage */
    $profile_storage = $this->entityTypeManager->getStorage('profile');
    $profile = $profile_storage->loadDefaultByUser($entity, $bundle);

    if ($profile) {
      if ($delta == 'NEW') {
        if ($name == "name") {
          return $entity->getDisplayName();
        }
        return NULL;
      }

      $item = $profile->get($field_name)->get($delta);
      if (!$item || $item->isEmpty() || !$item->entity) {
        return NULL;
      }

      $prop_field_name = ($field_name == 'email_addresses') ? 'email_address' : 'number';
      if ($name == "email" || $name == 'telephone') {
        return $item->entity->{$prop_field_name}->value;
      }
      else if ($name == "address") {
        return $item->entity->{$prop_field_name}->getItem(0)->toArray();
      }
      else if ($name == "name") {
        return $entity->getDisplayName();
      }
    }

    return NULL;
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
    list($bundle, $field_name, $delta) = explode('.', $key, 3);

    /** @var \Drupal\profile\ProfileStorageInterface $profile_storage */
    $profile_storage = $this->entityTypeManager->getStorage('profile');
    $profile = $profile_storage->loadDefaultByUser($entity, $bundle);

    $prop_field_name = ($field_name == 'email_addresses') ? 'email_address' : 'number';
    $paragraph_type = ($field_name == 'email_addresses') ? 'email_address': 'telphone_number';
    if ($profile) {
      if ($delta == 'NEW') {
        $paragraph = $this->entityTypeManager->getStorage('paragraph')->create([
          'type' => $paragraph_type,
        ]);
        $paragraph->{$prop_field_name} = ($field_name == 'email_addresses') ? $values['email'] : $values['telephone'];
        $profile->{$field_name}->appendItem($paragraph);
        $profile->save();

        return "{$bundle}.{$field_name}.{$profile->{$field_name}->count()}";
      }
      else {
        $item = $profile->{$field_name}->get($delta)->entity;
        foreach ($values as $vkey => $value) {
          if ($vkey == 'name') {
            continue;
          }
          else if ($vkey == 'metadata') {
            foreach ($value as $mkey => $mvalue) {
              $item->{$mkey} = $mvalue;
            }
          }
          else {
            $item->{$prop_field_name} = $values[$vkey];
          }
        }
        $item->save();
      }
      $profile->save();
    }

    return $key;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(EntityInterface $entity, $key) {
    list($bundle, $field_name, $delta) = explode('.', $key, 3);

    $profile_type = $this->entityTypeManager->getStorage('profile_type')->load($bundle);

    /** @var \Drupal\profile\ProfileStorageInterface $profile_storage */
    $profile_storage = $this->entityTypeManager->getStorage('profile');
    $profile = $profile_storage->loadDefaultByUser($entity, $bundle);

    if (is_numeric($delta)) {
      $item = $profile->get($field_name)->get($delta)->entity;
      $type_field = $field_name == 'email_addresses' ? 'email_type' : 'number_type';
      return $profile_type->label() . ' - ' . ucfirst($item->get($type_field)->value);
    }
    else {
      return $profile_type->label() . ' - New';
    }
  }

  /**
   * {@inheritdoc{
   */
  public function getSummary(EntityInterface $entity, $key) {
    list($bundle, $field_name, $delta) = explode('.', $key, 3);

    /** @var \Drupal\profile\ProfileStorageInterface $profile_storage */
    $profile_storage = $this->entityTypeManager->getStorage('profile');
    $profile = $profile_storage->loadDefaultByUser($entity, $bundle);

    if (is_numeric($delta)) {
      $item = $profile->get($field_name)->get($delta)->entity;
      $type_field = $field_name == 'email_addresses' ? 'email_type' : 'number_type';
      $prop_field_name = $field_name == 'email_addresses' ? 'email_address' : 'number';

      return $item->get($prop_field_name)->value . " (" . ucfirst($item->get($type_field)->value) . ")";
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function metadataForm(array $form, FormStateInterface $form_state, EntityInterface $entity, $sub_key) {
    $paragraph = $this->getParagraph($entity, $sub_key);
    if (!$paragraph) {
      return [];
    }

    if ($paragraph->getType() == 'email_address') {
      $fields = ['email_type', 'email_notes'];
    }
    else if ($paragraph->getType() == 'telephone_number') {
      $fields = ['number_type', 'is_direct_dial', 'number_notes'];
    }

    foreach ($fields as $field_name) {
      $widget = $this->widgetManager->getInstance([
        'field_definition' => $paragraph->getFieldDefinition($field_name),
        'form_mode' => 'default',
      ]);
      $form[$field_name] = $widget->form($paragraph->get($field_name), $form, $form_state);
    }

    return $form;
  }

  /**
   * Get the paragraph.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param $sub_key
   *
   * @return \Drupal\paragraphs\Entity\Paragraph|NULL
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function getParagraph(EntityInterface $entity, $sub_key) {
    list($bundle, $field_name, $delta) = explode('.', $sub_key, 3);

    /** @var \Drupal\profile\ProfileStorageInterface $profile_storage */
    $profile_storage = $this->entityTypeManager->getStorage('profile');
    $profile = $profile_storage->loadDefaultByUser($entity, $bundle);

    if (is_numeric($delta)) {
      return $profile->get($field_name)->get($delta)->entity;
    }
    else {
      $paragraph_type = ($field_name == 'email_addresses') ? 'email_address': 'telephone_number';
      return $this->entityTypeManager->getStorage('paragraph')->create([
        'type' => $paragraph_type,
      ]);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getInfoDefinition(EntityInterface $entity, $key) {
    list(,$field_name,) = explode('.', $key, 3);
    $data_type = $field_name == 'email_addresses' ? 'email' : 'telephone';

    return ContactInfoDefinition::create($data_type);
  }
}
