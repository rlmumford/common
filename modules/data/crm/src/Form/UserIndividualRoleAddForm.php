<?php

namespace Drupal\rlmcrm\Form;

class UserIndividualRoleAddForm extends UserRoleAddForm {

  /**
   * {@inheritdoc}
   */
  protected function prepareEntity() {
    parent::prepareEntity();

    $this->entity->addRole('individual');
  }

}
