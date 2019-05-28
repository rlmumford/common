<?php

namespace Drupal\rlmcrm\Form;

class UserOrganisationRoleAddForm extends UserRoleAddForm {

  /**
   * {@inheritdoc}
   */
  protected function prepareEntity() {
    parent::prepareEntity();

    $this->entity->addRole('organisation');
  }
}
