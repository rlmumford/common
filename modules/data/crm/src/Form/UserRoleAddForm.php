<?php

namespace Drupal\rlmcrm\Form;

use Drupal\Core\Entity\ContentEntityForm;

class UserRoleAddForm extends ContentEntityForm {

  /**
   * @var \Drupal\user\UserInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function prepareEntity() {
    parent::prepareEntity();

    list($op, $role) = explode('_', $this->operation, 2);
    $this->entity->addRole($role);
  }

}
