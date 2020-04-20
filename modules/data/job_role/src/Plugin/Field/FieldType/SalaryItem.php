<?php

namespace Drupal\job_role\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Class SalaryItem
 *
 * @FieldType(
 *   id = "job_role_salary",
 *   label = @Translation("Salary"),
 *   description = @Translation("Store salary information"),
 *   default_widget = "job_role_salary_default",
 *   default_formatter = "job_role_salary_description"
 * )
 *
 * @package Drupal\job_role\Plugin\Field\FieldType
 */
class SalaryItem extends FieldItemBase {

  /**
   * Salary type constants.
   */
  const TYPE_PH = 'ph';
  const TYPE_PD = 'pd';
  const TYPE_PW = 'pw';
  const TYPE_PA = 'pa';

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return parent::defaultFieldSettings() + [
          'allowed_currency_codes' => [
            'GBP' => 'GBP',
          ],
        ];
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element['allowed_currency_codes'] = [
      '#type' => 'select',
      '#multiple' => TRUE,
      '#title' => $this->t('Available Currencies'),
      '#options' => [
        'USD' => "United States Dollar",
        'GBP' => "British Pound",
      ],
      '#default_value' => $this->getSetting('allowed_currency_codes'),
    ];
    if (\Drupal::moduleHandler()->moduleExists('commerce_price')) {
      /** @var \CommerceGuys\Intl\Currency\CurrencyRepositoryInterface $currency_repository */
      $currency_repository = \Drupal::service('commerce_price.currency_repository');

      $options = [];
      foreach ($currency_repository->getAll() as $currency) {
        $options[$currency->getCurrencyCode()] = $currency->getName();
      }

      $element['allowed_currency_codes']['#options'] = $options;
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    if (empty($this->min)) {
      $this->min = '0';
    }
    if (empty($this->max)) {
      $this->max = '0';
    }

    parent::preSave();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'desc' => [
          'type' => 'text',
          'size' => 'big',
        ],
        'type' => [
          'type' => 'varchar',
          'length' => 2,
        ],
        'currency_code' => [
          'type' => 'varchar',
          'length' => 3,
        ],
        'min' => [
          'description' => 'The minimum amount.',
          'type' => 'numeric',
          'precision' => 19,
          'scale' => 6,
        ],
        'max' => [
          'description' => 'The maximum amount.',
          'type' => 'numeric',
          'precision' => 19,
          'scale' => 6,
        ],
      ],
      'indexes' => [
        'currency_code' => ['currency_code'],
        'type' => ['type'],
        'min' => ['min'],
        'max' => ['max'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];

    $properties['desc'] = DataDefinition::create('string')
      ->setLabel(t('Description'))
      ->setRequired(FALSE);
    $properties['currency_code'] = DataDefinition::create('string')
      ->setLabel(t('Currency code'))
      ->setRequired(FALSE);
    $properties['type'] = DataDefinition::create('string')
      ->setLabel(t('Type'))
      ->setRequired(FALSE);
    $properties['min'] = DataDefinition::create('string')
      ->setLabel(t('Minimum Value'))
      ->setRequired(FALSE);
    $properties['max'] = DataDefinition::create('string')
      ->setLabel(t('Maximum value'))
      ->setRequired(FALSE);

    return $properties;
  }

}
