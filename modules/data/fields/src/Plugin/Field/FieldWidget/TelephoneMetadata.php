<?php
/**
 * Created by PhpStorm.
 * User: Rob
 * Date: 22/06/2020
 * Time: 18:31
 */

namespace Drupal\rlm_fields\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\telephone\Plugin\Field\FieldWidget\TelephoneDefaultWidget;

/**
 * Plugin implementation of the 'telephone_default' widget.
 *
 * @FieldWidget(
 *   id = "telephone_metadata_default",
 *   label = @Translation("Telephone number (with metadata)"),
 *   field_types = {
 *     "telephone_metadata"
 *   }
 * )
 */
class TelephoneMetadata extends TelephoneDefaultWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'show_sms' => TRUE,
        'show_vm' => FALSE,
        'show_label' => TRUE,
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['show_label'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Label Input'),
      '#default_value' => (bool) $this->getSetting('show_label'),
    ];
    $element['show_sms'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show SMS Option'),
      '#default_value' => (bool) $this->getSetting('show_sms'),
    ];
    $element['show_vm'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show voice mail Option'),
      '#default_value' => (bool) $this->getSetting('show_vm'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['value']['#title'] = $this->t('Number');
    $element['value']['#title_display'] = 'before';
    $element['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $items[$delta]->label,
      '#access' => (bool) $this->getSetting('show_label'),
    ];
    $element['sms'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Can receive SMS?'),
      '#default_value' => !empty($items[$delta]->sms),
      '#access' => (bool) $this->getSetting('show_sms'),
    ];
    $element['vm'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Can receive voice mail?'),
      '#default_value' => !empty($items[$delta]->vm),
      '#access' => (bool) $this->getSetting('show_vm'),
    ];

    dpm($element);
    return $element;
  }

}
