<?php

namespace Drupal\checklist;

use Drupal\Core\TempStore\SharedTempStore;
use Drupal\Core\TempStore\SharedTempStoreFactory;

class ChecklistTempstoreRepository {

  /**
   * The shared tempstore factory.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * ChecklistTempstoreRepository constructor.
   *
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $temp_store_factory
   *   The shared tempstore factory.
   */
  public function __construct(SharedTempStoreFactory $temp_store_factory) {
    $this->tempStoreFactory = $temp_store_factory;
  }

  /**
   * Get the checklist from the tempstore if available.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *
   * @return \Drupal\checklist\ChecklistInterface
   */
  public function get(ChecklistInterface $checklist): ChecklistInterface {
    $key = $this->getKey($checklist);
    $tempstore = $this->getTempstore($checklist)->get($key);
    if (!empty($tempstore['checklist'])) {
      $checklist = $tempstore['checklist'];
    }
    return $checklist;
  }

  /**
   * Check whether the tempstore has a checklist.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *
   * @return bool
   */
  public function has(ChecklistInterface $checklist): bool {
    $tempstore = $this->getTempstore($checklist)->get($this->getKey($checklist));
    return !empty($tempstore['checklist']);
  }

  /**
   * Set the checklist in the tempstore.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function set(ChecklistInterface $checklist) {
    $this->getTempstore($checklist)->set(
      $this->getKey($checklist),
      ['checklist' => $checklist]
    );
  }

  /**
   * Delete the checklist from the tempstore.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function delete(ChecklistInterface $checklist) {
    $this->getTempstore($checklist)->delete($this->getKey($checklist));
  }

  /**
   * Get the key
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *
   * @return string
   */
  protected function getKey(ChecklistInterface $checklist): string {
    return "{$checklist->getEntity()->uuid()}:{$checklist->getKey()}";
  }

  /**
   * Get the tempstore
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *
   * @return \Drupal\Core\TempStore\SharedTempStore
   */
  protected function getTempstore(ChecklistInterface $checklist): SharedTempStore {
    $collection = 'entity_template.blueprint_storage.'.$checklist->getEntity()->getEntityTypeId();
    return $this->tempStoreFactory->get($collection);
  }

}
