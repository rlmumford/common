<?php

namespace Drupal\checklist;

use Drupal\Core\TempStore\SharedTempStore;
use Drupal\Core\TempStore\SharedTempStoreFactory;

/**
 * Tempstore repository for checklists.
 */
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
   *   The checklist to get from the tempstore.
   *
   * @return \Drupal\checklist\ChecklistInterface
   *   The checklist from the tempstore, or the checklist provided.
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
   *   The checklist.
   *
   * @return bool
   *   True if the checklists exists in the tempstore, false otherwise.
   */
  public function has(ChecklistInterface $checklist): bool {
    $tempstore = $this->getTempstore($checklist)->get($this->getKey($checklist));
    return !empty($tempstore['checklist']);
  }

  /**
   * Set the checklist in the tempstore.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The checklist.
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
   *   The checklist.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function delete(ChecklistInterface $checklist) {
    $this->getTempstore($checklist)->delete($this->getKey($checklist));
  }

  /**
   * Get the key.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The checklist.
   *
   * @return string
   *   The key of the checklist in the tempstore.
   */
  protected function getKey(ChecklistInterface $checklist): string {
    return "{$checklist->getEntity()->uuid()}:{$checklist->getKey()}";
  }

  /**
   * Get the tempstore.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The checklist.
   *
   * @return \Drupal\Core\TempStore\SharedTempStore
   *   The temstore containing the checklist.
   */
  protected function getTempstore(ChecklistInterface $checklist): SharedTempStore {
    $collection = 'checklist.checklist.' . $checklist->getEntity()->getEntityTypeId();
    return $this->tempStoreFactory->get($collection);
  }

}
