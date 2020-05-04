<?php

namespace Drupal\organization_user;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\organization\Entity\Organization;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class UserOrganizationResolver {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * @var \Symfony\Component\HttpFoundation\Session\Session
   */
  protected $session;

  /**
   * UserOrganizationResolver constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Session\AccountInterface $current_user
   * @param \Symfony\Component\HttpFoundation\Session\Session $session
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, Session $session) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->session = $session;
  }

  /**
   * @param \Drupal\Core\Session\AccountInterface $user
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\Core\Session\AccountInterface|null
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function ensureUser(AccountInterface $user) : UserInterface {
    if ($user instanceof UserInterface) {
      return $user;
    }

    return $this->entityTypeManager->getStorage('user')->load($user->id());
  }

  /**
   * Get the organization.
   *
   * @param \Drupal\Core\Session\AccountInterface|NULL $user
   *
   * @return \Drupal\organization\Entity\Organization|null
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getOrganization(AccountInterface $user = NULL) : ?Organization {
    if (!$user) {
      $user = $this->currentUser;
    }

    $user = $this->ensureUser($user);

    if ($user->organization->count() === 1) {
      return $user->organization->entity;
    }
    else {
      $delta = 0;
      if ($this->currentUser->id() === $user->id() && $this->session->has('current_organization')) {
        $delta = $this->session->get('current_organization');
      }

      return $user->organization[$delta]->entity;
    }
  }

}
