<?php
/**
 * Created by PhpStorm.
 * User: Rob
 * Date: 17/05/2019
 * Time: 18:28
 */

namespace Drupal\field_tools\Field;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\field_tools\Exception\UncallableOnFakeFieldStorageConfigException;

class FakeFieldStorageConfig implements FieldStorageConfigInterface {

  /**
   * @var \Drupal\Core\Field\FieldStorageDefinitionInterface
   */
  protected $fieldDefinition;

  /**
   * FakeFieldStorageConfig constructor.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_definition
   */
  public function __construct(FieldStorageDefinitionInterface $field_definition) {
    $this->fieldDefinition = $field_definition;
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation, AccountInterface $account = NULL, $return_as_object = FALSE) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return $this->fieldDefinition->getCacheContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return $this->fieldDefinition->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return $this->fieldDefinition->getCacheMaxAge();
  }

  /**
   * {@inheritdoc}
   */
  public function enable() {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function disable() {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function status() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isUninstalling() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function get($property_name) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * [@inheritdoc}
   */
  public function set($property_name, $value) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
  }

  /**
   * Gets the configuration dependencies.
   *
   * @return array
   *   An array of dependencies, keyed by $type.
   *
   * @see \Drupal\Core\Config\Entity\ConfigDependencyManager
   */
  public function getDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isInstallable() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function trustData() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasTrustedData() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function uuid() {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function language() {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function isNew() {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function enforceIsNew($value = TRUE) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId() {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function bundle() {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $this->fieldDefinition->getLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function urlInfo($rel = 'canonical', array $options = []) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function url($rel = 'canonical', $options = []) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function link($text = NULL, $rel = 'canonical', array $options = []) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function toLink($text = NULL, $rel = 'canonical', array $options = []) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function hasLinkTemplate($key) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function uriRelationships() {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public static function load($id) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public static function loadMultiple(array $ids = NULL) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(array $values = []) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function postCreate(EntityStorageInterface $storage) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public static function postLoad(EntityStorageInterface $storage, array &$entities) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}.
   */
  public function createDuplicate() {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType() {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function referencedEntities() {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalId() {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTagsToInvalidate() {
    $this->fieldDefinition->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function setOriginalId($id) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function getTypedData() {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigDependencyKey() {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigDependencyName() {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * [@inheritdoc}
   */
  public function getConfigTarget() {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}.
   */
  public function getType() {
    return $this->fieldDefinition->getType();
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeProvider() {
    $definition = \Drupal::service('plugin.manager.field.field_type')->getDefinition($this->getType());
    return $definition['provider'];
  }

  /**
   * Returns the list of bundles where the field storage has fields.
   *
   * @return array
   *   An array of bundle names.
   */
  public function getBundles() {
    if ($this->fieldDefinition instanceof BaseFieldDefinition) {
      return array_keys(
        \Drupal::service('entity_type.bundle.info')->getBundleInfo($this->fieldDefinition->getTargetEntityTypeId())
      );
    }

    return [];
  }

  /**
   * [@inheritdoc}
   */
  public function isDeletable() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isLocked() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setLocked($locked) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function setCardinality($cardinality) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function setSetting($setting_name, $value) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function setSettings(array $settings) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function setTranslatable($translatable) {
    return $this->fieldDefinition->isTranslatable();
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexes() {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function setIndexes(array $indexes) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->fieldDefinition->getName();
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    return $this->fieldDefinition->getSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting($setting_name) {
    return $this->fieldDefinition->getSetting($setting_name);
  }

  /**
   * Returns whether the field supports translation.
   *
   * @return bool
   *   TRUE if the field supports translation.
   */
  public function isTranslatable() {
    return $this->fieldDefinition->isTranslatable();
  }

  /**
   * Returns whether the field storage is revisionable.
   *
   * Note that if the entity type is revisionable and the field storage has a
   * cardinality higher than 1, the field storage is considered revisionable
   * by default.
   *
   * @return bool
   *   TRUE if the field is revisionable.
   */
  public function isRevisionable() {
    return $this->fieldDefinition->isRevisionable();
  }

  /**
   * Determines whether the field is queryable via QueryInterface.
   *
   * @return bool
   *   TRUE if the field is queryable.
   *
   * @deprecated in Drupal 8.4.0 and will be removed before Drupal 9.0.0. Use
   *   \Drupal\Core\Field\FieldStorageDefinitionInterface::hasCustomStorage()
   *   instead.
   *
   * @see https://www.drupal.org/node/2856563
   */
  public function isQueryable() {
    return $this->fieldDefinition->isQueryable();
  }

  /**
   * Returns the human-readable label for the field.
   *
   * @return string
   *   The field label.
   */
  public function getLabel() {
    return $this->fieldDefinition->getLabel();
  }

  /**
   * Returns the human-readable description for the field.
   *
   * This is displayed in addition to the label in places where additional
   * descriptive information is helpful. For example, as help text below the
   * form element in entity edit forms.
   *
   * @return string|null
   *   The field description, or NULL if no description is available.
   */
  public function getDescription() {
    return $this->fieldDefinition->getDescription();
  }

  /**
   * Gets an options provider for the given field item property.
   *
   * @param string $property_name
   *   The name of the property to get options for; e.g., 'value'.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity for which the options should be provided.
   *
   * @return \Drupal\Core\TypedData\OptionsProviderInterface|null
   *   An options provider, or NULL if no options are defined.
   */
  public function getOptionsProvider($property_name, FieldableEntityInterface $entity) {
    return $this->fieldDefinition->getOptionsProvider($property_name, $entity);
  }

  /**
   * Returns whether the field can contain multiple items.
   *
   * @return bool
   *   TRUE if the field can contain multiple items, FALSE otherwise.
   */
  public function isMultiple() {
    return $this->fieldDefinition->isMultiple();
  }

  /**
   * Returns the maximum number of items allowed for the field.
   *
   * Possible values are positive integers or
   * FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED.
   *
   * @return int
   *   The field cardinality.
   */
  public function getCardinality() {
    return $this->fieldDefinition->getCardinality();
  }

  /**
   * Gets the definition of a contained property.
   *
   * @param string $name
   *   The name of property.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface|null
   *   The definition of the property or NULL if the property does not exist.
   */
  public function getPropertyDefinition($name) {
    return $this->fieldDefinition->getPropertyDefinition($name);
  }

  /**
   * Gets an array of property definitions of contained properties.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   An array of property definitions of contained properties, keyed by
   *   property name.
   */
  public function getPropertyDefinitions() {
    return $this->fieldDefinition->getPropertyDefinitions();
  }

  /**
   * Returns the names of the field's subproperties.
   *
   * A field is a list of items, and each item can contain one or more
   * properties. All items for a given field contain the same property names,
   * but the values can be different for each item.
   *
   * For example, an email field might just contain a single 'value' property,
   * while a link field might contain 'title' and 'url' properties, and a text
   * field might contain 'value', 'summary', and 'format' properties.
   *
   * @return string[]
   *   The property names.
   */
  public function getPropertyNames() {
    return $this->fieldDefinition->getPropertyNames();
  }

  /**
   * Returns the name of the main property, if any.
   *
   * Some field items consist mainly of one main property, e.g. the value of a
   * text field or the @code target_id @endcode of an entity reference. If the
   * field item has no main property, the method returns NULL.
   *
   * @return string|null
   *   The name of the value property, or NULL if there is none.
   */
  public function getMainPropertyName() {
    return $this->fieldDefinition->getMainPropertyName();
  }

  /**
   * Returns the ID of the entity type the field is attached to.
   *
   * This method should not be confused with EntityInterface::getEntityTypeId()
   * (configurable fields are config entities, and thus implement both
   * interfaces):
   *   - FieldStorageDefinitionInterface::getTargetEntityTypeId() answers "as a
   *     field storage, which entity type are you attached to?".
   *   - EntityInterface::getEntityTypeId() answers "as a (config) entity, what
   *     is your own entity type?".
   *
   * @return string
   *   The entity type ID.
   */
  public function getTargetEntityTypeId() {
    return $this->fieldDefinition->getTargetEntityTypeId();
  }

  /**
   * Returns the field schema.
   *
   * Note that this method returns an empty array for computed fields which have
   * no schema.
   *
   * @return array[]
   *   The field schema, as an array of key/value pairs in the format returned
   *   by \Drupal\Core\Field\FieldItemInterface::schema():
   *   - columns: An array of Schema API column specifications, keyed by column
   *     name. This specifies what comprises a single value for a given field.
   *     No assumptions should be made on how storage backends internally use
   *     the original column name to structure their storage.
   *   - indexes: An array of Schema API index definitions. Some storage
   *     backends might not support indexes.
   *   - unique keys: An array of Schema API unique key definitions.  Some
   *     storage backends might not support unique keys.
   *   - foreign keys: An array of Schema API foreign key definitions. Note,
   *     however, that depending on the storage backend specified for the field,
   *     the field data is not necessarily stored in SQL.
   */
  public function getSchema() {
    return $this->fieldDefinition->getSchema();
  }

  /**
   * Returns the field columns, as defined in the field schema.
   *
   * @return array[]
   *   The array of field columns, keyed by column name, in the same format
   *   returned by getSchema().
   *
   * @see \Drupal\Core\Field\FieldStorageDefinitionInterface::getSchema()
   */
  public function getColumns() {
    return $this->fieldDefinition->getColumns();
  }

  /**
   * Returns an array of validation constraints.
   *
   * See \Drupal\Core\TypedData\DataDefinitionInterface::getConstraints() for
   * details.
   *
   * @return array[]
   *   An array of validation constraint definitions, keyed by constraint name.
   *   Each constraint definition can be used for instantiating
   *   \Symfony\Component\Validator\Constraint objects.
   *
   * @see \Symfony\Component\Validator\Constraint
   */
  public function getConstraints() {
    return $this->fieldDefinition->getConstraints();
  }

  /**
   * Returns a validation constraint.
   *
   * See \Drupal\Core\TypedData\DataDefinitionInterface::getConstraints() for
   * details.
   *
   * @param string $constraint_name
   *   The name of the constraint, i.e. its plugin id.
   *
   * @return array
   *   A validation constraint definition which can be used for instantiating a
   *   \Symfony\Component\Validator\Constraint object.
   *
   * @see \Symfony\Component\Validator\Constraint
   */
  public function getConstraint($constraint_name) {
    return $this->fieldDefinition->getConstraint($constraint_name);
  }

  /**
   * Returns the name of the provider of this field.
   *
   * @return string
   *   The provider name; e.g., the module name.
   */
  public function getProvider() {
    return $this->fieldDefinition->getProvider();
  }

  /**
   * Returns the storage behavior for this field.
   *
   * Indicates whether the entity type's storage should take care of storing the
   * field values or whether it is handled separately; e.g. by the
   * module providing the field.
   *
   * @return bool
   *   FALSE if the storage takes care of storing the field, TRUE otherwise.
   */
  public function hasCustomStorage() {
    return $this->fieldDefinition->hasCustomStorage();
  }

  /**
   * Determines whether the field is a base field.
   *
   * Base fields are not specific to a given bundle or a set of bundles. This
   * excludes configurable fields, as they are always attached to a specific
   * bundle.
   *
   * @return bool
   *   Whether the field is a base field.
   */
  public function isBaseField() {
    return $this->fieldDefinition->isBaseField();
  }

  /**
   * Returns a unique identifier for the field storage.
   *
   * @return string
   */
  public function getUniqueStorageIdentifier() {
    return $this->fieldDefinition->getUniqueStorageIdentifier();
  }

  /**
   * Returns whether the field is deleted or not.
   *
   * @return bool
   *   TRUE if the field is deleted, FALSE otherwise.
   */
  public function isDeleted() {
    return FALSE;
  }

  /**
   * Adds cache contexts.
   *
   * @param string[] $cache_contexts
   *   The cache contexts to be added.
   *
   * @return $this
   */
  public function addCacheContexts(array $cache_contexts) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * Adds cache tags.
   *
   * @param string[] $cache_tags
   *   The cache tags to be added.
   *
   * @return $this
   */
  public function addCacheTags(array $cache_tags) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * Merges the maximum age (in seconds) with the existing maximum age.
   *
   * The max age will be set to the given value if it is lower than the existing
   * value.
   *
   * @param int $max_age
   *   The max age to associate.
   *
   * @return $this
   *
   * @throws \InvalidArgumentException
   *   Thrown if a non-integer value is supplied.
   */
  public function mergeCacheMaxAge($max_age) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * Adds a dependency on an object: merges its cacheability metadata.
   *
   * @param \Drupal\Core\Cache\CacheableDependencyInterface|object $other_object
   *   The dependency. If the object implements CacheableDependencyInterface,
   *   then its cacheability metadata will be used. Otherwise, the passed in
   *   object must be assumed to be uncacheable, so max-age 0 is set.
   *
   * @return $this
   *
   * @see \Drupal\Core\Cache\CacheableMetadata::createFromObject()
   */
  public function addCacheableDependency($other_object) {
    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * Sets the status of the synchronization flag.
   *
   * @param bool $status
   *   The status of the synchronization flag.
   *
   * @return $this
   */
  public function setSyncing($status) {

    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * Returns whether this entity is being changed as part of a synchronization.
   *
   * If you are writing code that responds to a change in this entity (insert,
   * update, delete, presave, etc.), and your code would result in a change to
   * this entity itself, a configuration change (whether related to this entity,
   * another entity, or non-entity configuration), you need to check and see if
   * this entity change is part of a synchronization process, and skip executing
   * your code if that is the case.
   *
   * For example, \Drupal\node\Entity\NodeType::postSave() adds the default body
   * field to newly created node type configuration entities, which is a
   * configuration change. You would not want this code to run during an import,
   * because imported entities were already given the body field when they were
   * originally created, and the imported configuration includes all of their
   * currently-configured fields. On the other hand,
   * \Drupal\field\Entity\FieldStorageConfig::preSave() and the methods it calls
   * make sure that the storage tables are created or updated for the field
   * storage configuration entity, which is not a configuration change, and it
   * must be done whether due to an import or not. So, the first method should
   * check $entity->isSyncing() and skip executing if it returns TRUE, and the
   * second should not perform this check.
   *
   * @return bool
   *   TRUE if the configuration entity is being created, updated, or deleted
   *   through a synchronization process.
   */
  public function isSyncing() {

    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * Sets the value of a third-party setting.
   *
   * @param string $module
   *   The module providing the third-party setting.
   * @param string $key
   *   The setting name.
   * @param mixed $value
   *   The setting value.
   *
   * @return $this
   */
  public function setThirdPartySetting($module, $key, $value) {

    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * Gets the value of a third-party setting.
   *
   * @param string $module
   *   The module providing the third-party setting.
   * @param string $key
   *   The setting name.
   * @param mixed $default
   *   The default value
   *
   * @return mixed
   *   The value.
   */
  public function getThirdPartySetting($module, $key, $default = NULL) {

    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * Gets all third-party settings of a given module.
   *
   * @param string $module
   *   The module providing the third-party settings.
   *
   * @return array
   *   An array of key-value pairs.
   */
  public function getThirdPartySettings($module) {

    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * Unsets a third-party setting.
   *
   * @param string $module
   *   The module providing the third-party setting.
   * @param string $key
   *   The setting name.
   *
   * @return mixed
   *   The value.
   */
  public function unsetThirdPartySetting($module, $key) {

    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }

  /**
   * Gets the list of third parties that store information.
   *
   * @return array
   *   The list of third parties.
   */
  public function getThirdPartyProviders() {

    throw UncallableOnFakeFieldStorageConfigException::createFromMethod(__METHOD__);
  }
}
