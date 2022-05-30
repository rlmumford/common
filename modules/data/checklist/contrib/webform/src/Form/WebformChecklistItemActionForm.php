<?php

namespace Drupal\checklist_webform\Form;

use Drupal\checklist\Form\ChecklistItemActionForm;
use Drupal\webform\WebformSubmissionForm;

/**
 * A form class to use for webform checklist item action forms.
 */
class WebformChecklistItemActionForm extends ChecklistItemActionForm {

  /**
   * The webform form object.
   *
   * @var \Drupal\webform\WebformSubmissionForm
   */
  protected WebformSubmissionForm $webformFormObject;

  /**
   * Set the webform form object.
   *
   * @param \Drupal\webform\WebformSubmissionForm $webform_form
   *   The webform form.
   */
  public function setWebformFormObject(WebformSubmissionForm $webform_form) {
    $this->webformFormObject = $webform_form;
  }

  /**
   * Pass undefined methods onto the webform object.
   *
   * @param string $name
   *   The name of the method.
   * @param array $arguments
   *   The arguments.
   *
   * @return mixed
   *   Whatever the method on the webform form object returns.
   */
  public function __call(string $name, array $arguments) {
    if (!method_exists($this->webformFormObject, $name)) {
      throw new \BadMethodCallException('Call to undefined method ' . get_class($this->webformFormObject) . "::{$name}()", 0);
    }

    return $this->webformFormObject->{$name}(...$arguments);
  }

}
