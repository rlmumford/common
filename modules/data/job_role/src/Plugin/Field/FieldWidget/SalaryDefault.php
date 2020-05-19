<?php

namespace Drupal\job_role\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\job_role\Plugin\Field\FieldType\SalaryItem;

/**
 * Class SalaryDefault
 *
 * @FieldWidget(
 *   id = "job_role_salary_default",
 *   label = @Translation("Default Salary Widget"),
 *   field_types = {
 *     "job_role_salary"
 *   }
 * )
 *
 * @package Drupal\job_role\Plugin\Field\FieldWidget
 */
class SalaryDefault extends WidgetBase {

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
    $element += [
      '#type' => 'fieldset',
      '#attributes' => [
        'class' => 'salary-widget-default',
      ],
      '#attached' => [
        'library' => [
          'job_role/job-role.salary.widget',
        ]
      ]
    ];
    $element['min'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Minimum'),
      '#title_display' => 'invisible',
      '#size' => 10,
      '#default_value' => number_format((float) $items->get($delta)->min, 2),
      '#wrapper_attributes' => [
        'class' => ['inline-item'],
      ]
    ];
    $element['max'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum'),
      '#title_display' => 'invisible',
      '#size' => 10,
      '#default_value' => number_format((float) $items->get($delta)->max, 2),
      '#wrapper_attributes' => [
        'class' => ['inline-item'],
      ]
    ];
    $element['currency_code'] = [
      '#type' => 'value',
      '#value' => $items->get($delta)->currency_code,
      '#wrapper_attributes' => [
        'class' => ['inline-item'],
      ]
    ];

    if (\Drupal::moduleHandler()->moduleExists('commerce_price')) {
      /** @var \CommerceGuys\Intl\Currency\CurrencyRepositoryInterface $currency_repository */
      $currency_repository = \Drupal::service('commerce_price.currency_repository');
    }

    $currency_codes = $items->getFieldDefinition()->getSetting('allowed_currency_codes');
    if (count($currency_codes) === 1) {
      $ccode_prefix = $ccode_suffix = "";

      if ($currency_repository) {
        $currency = $currency_repository->get(key($currency_codes));
        $ccode_prefix = $currency->getSymbol();
      }
      else {
        $ccode_suffix = key($currency_codes);
      }

      $element['min']['#field_prefix'] = $this->t('Between @code', ['@code' => $ccode_prefix]);
      $element['min']['#field_suffix'] = $ccode_suffix;
      $element['max']['#field_prefix'] = $this->t('and @code', ['@code' => $ccode_prefix]);
      $element['max']['#field_suffic'] = $ccode_suffix;

      if (empty($element['currency_code']['#value'])) {
        $element['currency_code']['#value'] = key($currency_codes);
      }
    }
    else {
      $element['min']['#field_prefix'] = $this->t('Between');
      $element['max']['#field_prefix'] = $this->t('and');

      $currency_options = [];
      foreach ($currency_codes as $currency_code) {
        $currency_options[$currency_code] = $currency_repository->get($currency_code)->getName();
      }

      $element['currency_code'] = [
        '#type' => 'select',
        '#title' => $this->t('Currency'),
        '#title_display' => 'invisible',
        '#options' => $currency_options,
        '#default_value' => $items->get($delta)->currency_code,
        '#wrapper_attributes' => [
          'class' => ['inline-item'],
        ]
      ];
    }

    $element['type'] = [
      '#type' => 'select',
      '#title' => $this->t("Period"),
      '#title_display' => 'invisible',
      '#options' => [
        SalaryItem::TYPE_PH => $this->t('per Hour'),
        SalaryItem::TYPE_PD => $this->t('per Day'),
        SalaryItem::TYPE_PW => $this->t('per Week'),
        SalaryItem::TYPE_PM => $this->t('per Month'),
        SalaryItem::TYPE_PA => $this->t('per Annum'),
      ],
      '#default_value' => $items->get($delta)->type,
      '#wrapper_attributes' => [
        'class' => ['inline-item'],
      ]
    ];

    $element['desc'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#size' => 3,
      '#default_value' => $items->get($delta)->get('desc')->getValue(),
    ];

    return $element;
  }
}
