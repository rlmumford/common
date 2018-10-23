<?php

namespace Drupal\field_tools\Plugin\Field\FieldWidget;

use Drupal\address\Plugin\Field\FieldWidget\AddressDefaultWidget as AddressAddressDefaultWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

class AddressDefaultWidget extends AddressAddressDefaultWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $item = $items[$delta];
    $value = empty($item->toArray()['country_code']) ? $this->getInitialValues() : $item->toArray();
    // Calling initializeLangcode() every time, and not just when the field
    // is empty, ensures that the langcode can be changed on subsequent
    // edits (because the entity or interface language changed, for example).
    $value['langcode'] = $item->initializeLangcode();

    $element += [
      '#type' => 'details',
      '#collapsible' => TRUE,
      '#open' => TRUE,
    ];
    $element['address'] = [
      '#type' => 'address',
      '#default_value' => $value,
      '#required' => $this->fieldDefinition->isRequired(),
      '#available_countries' => $item->getAvailableCountries(),
      '#field_overrides' => $item->getFieldOverrides(),
    ];

    return $element;
  }
}
