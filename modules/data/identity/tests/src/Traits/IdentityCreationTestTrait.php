<?php

namespace Drupal\Tests\identity\Traits;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Trait for tests that require identities to be created.
 */
trait IdentityCreationTestTrait {

  /**
   * The identity storage.
   *
   * @var \Drupal\identity\Entity\IdentityStorage
   */
  protected $identityStorage = NULL;

  /**
   * The identity data storage.
   *
   * @var \Drupal\identity\Entity\IdentityDataStorage
   */
  protected $identityDataStorage = NULL;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|null
   */
  protected ?EntityTypeManagerInterface $entityTypeManager = NULL;

  /**
   * Installs the storage schema for a specific entity type.
   *
   * @param string $entity_type_id
   *   The ID of the entity type.
   */
  abstract protected function installEntitySchema($entity_type_id);

  /**
   * Get the identity storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface|\Drupal\identity\Entity\IdentityStorage|null
   *   The identity storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getIdentityStorage() {
    if (!$this->identityStorage) {
      $this->identityStorage = $this->entityTypeManager->getStorage('identity');
    }

    return $this->identityStorage;
  }

  /**
   * Get the identity data storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface|\Drupal\identity\Entity\IdentityDataStorage|null
   *   The storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getIdentityDataStorage() {
    if (!$this->identityDataStorage) {
      $this->identityDataStorage = $this->entityTypeManager->getStorage('identity_data');
    }

    return $this->identityDataStorage;
  }

  /**
   * Set up the test.
   */
  protected function setUpIdentityCreationTrait() : void {
    $this->installEntitySchema('identity');
    $this->installEntitySchema('identity_data');

    $this->installConfig(['name']);

    $this->entityTypeManager = \Drupal::entityTypeManager();
  }

  /**
   * Create an identity with a given personal name.
   *
   * @param string|null $first_name
   *   The first name, or NULL if a random name should be used.
   * @param string|null $last_name
   *   The last name, or NULL if a random name should be used.
   *
   * @return \Drupal\identity\Entity\Identity
   *   The identity that has been created.
   */
  public function createIdentityWithPersonalName($first_name = NULL, $last_name = NULL) {
    $first_name = $first_name ?: $this->randomMachineName();
    $last_name = $last_name ?: $this->randomMachineName();

    /** @var \Drupal\identity\Entity\Identity $identity */
    $identity = $this->getIdentityStorage()->create([]);
    $identity_data = $this->getIdentityDataStorage()->create([
      'class' => 'personal_name',
      'identity' => $identity,
      'full_name' => "{$first_name} {$last_name}",
      'name' => [
        'given' => $first_name,
        'family' => $last_name,
      ],
    ]);
    $identity_data->save();

    return $identity;
  }

}
