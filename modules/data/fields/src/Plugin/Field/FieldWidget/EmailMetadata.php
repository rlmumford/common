<?php

namespace Drupal\rlm_fields\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EmailDefaultWidget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'email_metadata_default' widget.
 *
 * @FieldWidget(
 *   id = "email_metadata_default",
 *   label = @Translation("Email (with Metadata)"),
 *   field_types = {
 *     "email_metadata"
 *   }
 * )
 */
class EmailMetadata extends EmailDefaultWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['value']['#title'] = $this->t('Email Address');
    $element['value']['#title_display'] = 'before';
    $element['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $items[$delta]->label,
    ];

    return $element;
  }
}
