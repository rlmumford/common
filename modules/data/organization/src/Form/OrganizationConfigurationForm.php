<?php

namespace Drupal\organization\Form;

use Drupal\Core\Form\ConfigFormBase;

class OrganizationConfigurationForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'organization_configuration_form';
  }
}
