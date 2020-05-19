<?php

namespace Drupal\field_tools\Plugin\Field\FieldWidget;

use CommerceGuys\Addressing\AddressFormat\AddressField;
use CommerceGuys\Addressing\AddressFormat\FieldOverride;
use CommerceGuys\Addressing\Country\CountryRepositoryInterface;
use Drupal\address\Plugin\Field\FieldWidget\AddressDefaultWidget;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\geocoder\DumperPluginManager;
use Drupal\geocoder\Geocoder;
use Drupal\geocoder_field\PreprocessorPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class GeofieldAddress
 *
 * @FieldWidget(
 *   id = "geofield_address",
 *   label = @Translation("Address"),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 *
 * @package Drupal\field_tools\Plugin\Field\FieldWidget
 */
class GeofieldAddress extends AddressDefaultWidget implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\geocoder_field\PreprocessorPluginManager
   */
  protected $preprocessorManager;

  /**
   * @var \Drupal\geocoder\DumperPluginManager
   */
  protected $dumperManager;

  /**
   * @var \Drupal\geocoder\Geocoder
   */
  protected $geocoder;

  /**
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $keyValueStore;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.geocoder.preprocessor'),
      $container->get('plugin.manager.geocoder.dumper'),
      $container->get('geocoder'),
      $container->get('keyvalue')->get('geofield_address_widget_cache'),
      $container->get('address.country_repository'),
      $container->get('event_dispatcher'),
      $container->get('config.factory')
    );
  }

  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    PreprocessorPluginManager $preprocessor_manager,
    DumperPluginManager $dumper_manager,
    Geocoder $geocoder,
    KeyValueStoreInterface $key_value_store,
    CountryRepositoryInterface $country_repository,
    EventDispatcherInterface $event_dispatcher,
    ConfigFactoryInterface $config_factory
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $country_repository, $event_dispatcher, $config_factory);

    $this->preprocessorManager = $preprocessor_manager;
    $this->dumperManager = $dumper_manager;
    $this->geocoder = $geocoder;
    $this->keyValueStore = $key_value_store;
  }

  /**
   * Returns the form for a single field widget.
   *
   * Field widget form elements should be based on the passed-in $element, which
   * contains the base form element properties derived from the field
   * configuration.
   *
   * The BaseWidget methods will set the weight, field name and delta values for
   * each form element. If there are multiple values for this field, the
   * formElement() method will be called as many times as needed.
   *
   * Other modules may alter the form element provided by this function using
   * hook_field_widget_form_alter() or
   * hook_field_widget_WIDGET_TYPE_form_alter().
   *
   * The FAPI element callbacks (such as #process, #element_validate,
   * #value_callback, etc.) used by the widget do not have access to the
   * original $field_definition passed to the widget's constructor. Therefore,
   * if any information is needed from that definition by those callbacks, the
   * widget implementing this method, or a
   * hook_field_widget[_WIDGET_TYPE]_form_alter() implementation, must extract
   * the needed properties from the field definition and set them as ad-hoc
   * $element['#custom'] properties, for later use by its element callbacks.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Array of default values for this field.
   * @param int $delta
   *   The order of this item in the array of sub-elements (0, 1, 2, etc.).
   * @param array $element
   *   A form element array containing basic properties for the widget:
   *   - #field_parents: The 'parents' space for the field in the form. Most
   *       widgets can simply overlook this property. This identifies the
   *       location where the field values are placed within
   *       $form_state->getValues(), and is used to access processing
   *       information for the field through the getWidgetState() and
   *       setWidgetState() methods.
   *   - #title: The sanitized element label for the field, ready for output.
   *   - #description: The sanitized element description for the field, ready
   *     for output.
   *   - #required: A Boolean indicating whether the element value is required;
   *     for required multiple value fields, only the first widget's values are
   *     required.
   *   - #delta: The order of this item in the array of sub-elements; see $delta
   *     above.
   * @param array $form
   *   The form structure where widgets are being attached to. This might be a
   *   full form structure, or a sub-element of a larger form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form elements for a single widget for this field.
   *
   * @see hook_field_widget_form_alter()
   * @see hook_field_widget_WIDGET_TYPE_form_alter()
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $item = $items[$delta];
    $value = [];

    if ($item->value) {
      $info = json_decode($item->value, TRUE);

      if (!empty($info['properties']['streetName'])) {
        $value['address_line1'] = $info['properties']['streetNumber'].' '.$info['properties']['streetName'];
      }
      if (!empty($info['properties']['postalCode'])) {
        $value['postal_code'] = $info['properties']['postalCode'];
      }
      if (!empty($info['properties']['countryCode'])) {
        $value['country_code'] = $info['properties']['countryCode'];

        // @todo: Better convert administrative areas into address parts.
        if ($info['properties']['countryCode'] === 'GB' && isset($info['properties']['adminLevels'][2])) {
          $value['administrative_area'] = $info['properties']['adminLevels'][2]['name'];
        }
      }
    }

    $element['address'] = [
      '#type' => 'address',
      '#default_value' => $value,
      '#required' => $this->fieldDefinition->isRequired(),
      '#field_overrides' => [
        AddressField::GIVEN_NAME => FieldOverride::HIDDEN,
        AddressField::FAMILY_NAME => FieldOverride::HIDDEN,
        AddressField::ADDITIONAL_NAME => FieldOverride::HIDDEN,
        AddressField::LOCALITY => FieldOverride::OPTIONAL,
      ],
    ];
    // Make sure no properties are required on the default value widget.
    if ($this->isDefaultValueWidget($form_state)) {
      $element['address']['#after_build'][] = [get_class($this), 'makeFieldsOptional'];
    }

    return $element;
  }

  /**
   * @param array $values
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $spoof_values = [];
    foreach ($values as $delta => $value) {
      $spoof_values[$delta] = $value['address'];
    }
    $spoof_definition = BaseFieldDefinition::create('address');
    $spoof_items = FieldItemList::createInstance($spoof_definition, 'address');
    $spoof_items->setValue($spoof_values);

    $this->preprocessorManager->preprocess($spoof_items);

    // @todo: Find a way of checking values.
    $dumper = $this->dumperManager->createInstance('geojson');

    foreach ($spoof_items->getValue() as $delta => $value) {
      if (!isset($value['value'])) {
        $values[$delta] = NULL;
        continue;
      }

      $hk = md5($value['value']);
      if ($this->keyValueStore->has($hk)) {
        $values[$delta] = $this->keyValueStore->get($hk);
        continue;
      }

      $address_collection = $this->geocoder->geocode($value['value'], ['googlemaps', 'googlemaps_business']);
      if ($address_collection) {
        $values[$delta] = $dumper->dump($address_collection->first());

        // We can't use DumperPluginManager::fixDumperFieldIncompatibility
        // because we do not have a FieldConfigInterface.
        // Fix not UTF-8 encoded result strings.
        // https://stackoverflow.com/questions/6723562/how-to-detect-malformed-utf-8-string-in-php
        if (is_string($values[$delta])) {
          if (!preg_match('//u', $values[$delta])) {
            $values[$delta] = utf8_encode($values[$delta]);
          }
        }

        $this->keyValueStore->set($hk, $values[$delta]);
      }
    }

    return $values;
  }
}
