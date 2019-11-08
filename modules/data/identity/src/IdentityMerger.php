<?php

namespace Drupal\identity;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\identity\Entity\Identity;
use Drupal\identity\Event\IdentityEvents;
use Drupal\identity\Event\PostIdentityMergeEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class IdentityMerger implements IdentityMergerInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\identity\Entity\IdentityStorage
   */
  protected $identityStorage;

  /**
   * @var \Drupal\identity\Entity\IdentityDataStorage
   */
  protected $identityDataStorage;

  /**
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * IdentityMerger constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher) {
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->identityStorage = $this->entityTypeManager->getStorage('identity');
    $this->identityDataStorage = $this->entityTypeManager->getStorage('identity_data');
  }

  /**
   * {@inheritdoc}
   */
  public function mergeIdentities(Identity $identity_one, Identity $identity_two) {
    foreach ($identity_two->getAllData() as $data) {
      $data->setIdentity($identity_one);
      $data->setNewRevision(TRUE);
      $data->skipIdentitySave(TRUE);
      $data->save();
    }

    $identity_two->state->value = FALSE;
    $identity_two->save();

    $event = new PostIdentityMergeEvent($identity_one, $identity_two, $identity_one);
    $this->eventDispatcher->dispatch(IdentityEvents::POST_MERGE, $event);

    return $identity_one;
  }

}
