<?php

namespace Drupal\job_role\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\organization\Entity\EntityOrganizationInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for job_roles.
 */
interface JobRoleInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets whether the job_role is active.
   *
   * Unpublished job_roles are only visible to their authors and administrators.
   *
   * @return bool
   *   TRUE if the job_role is active, FALSE otherwise.
   */
  public function isActive();

  /**
   * Sets whether the job_role is active.
   *
   * @param bool $active
   *   Whether the job_role is active.
   *
   * @return $this
   */
  public function setActive($active);

  /**
   * Gets the job_role creation timestamp.
   *
   * @return int
   *   The job_role creation timestamp.
   */
  public function getCreatedTime();

  /**
   * Sets the job_role creation timestamp.
   *
   * @param int $timestamp
   *   The job_role creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime($timestamp);

  /**
   * Get the organization this entity belongs to.
   *
   * @return \Drupal\organization\Entity\Organization
   */
  public function getOrganization();

  /**
   * Set the organization.
   *
   * @param \Drupal\organization\Entity\Organization $organization
   *
   * @return static
   */
  public function setOrganization(EntityInterface $organization);

}
