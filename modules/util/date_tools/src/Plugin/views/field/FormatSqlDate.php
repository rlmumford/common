<?php

namespace Drupal\date_tools\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\views\Plugin\views\field\Date;
use Drupal\views\ResultRow;

/**
 * Date field to change date format in SQL query.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("date_tools_format_sql_date")
 */
class FormatSqlDate extends Date {

  /**
   * Sets date format from field options.
   *
   * @return string
   */
  protected function getStringFormat() {
    $format = $this->options['date_format'];
    if (empty($format)) {
      return '';
    }

    if ($format === 'custom') {
      return !empty($this->options['custom_date_format']) ? $this->options['custom_date_format'] : '';
    }

    $formatter = DateFormat::load($format);
    return empty($formatter) ? '' : $formatter->getPattern();
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    return [
      'format_date_sql' => ['default' => FALSE]
    ] + parent::defineOptions();
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['format_date_sql'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Use SQL to format date'),
      '#description' => $this->t('Use the SQL database to format the date. This enables date values to be used in grouping aggregation.'),
      '#default_value' => $this->options['format_date_sql'],
    );

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (empty($this->options['format_date_sql'])) {
      return parent::query();
    }

    $this->ensureMyTable();
    $format = $this->getStringFormat();
    $formula = $this->query->getDateFormat($this->tableAlias . '.' . $this->realField, $format);

    $params = $this->options['group_type'] != 'group' ? ['function' => $this->options['group_type']] : [];
    $this->field_alias = $this->query->addField(NULL, $formula, $this->tableAlias . '_' . $this->realField, $params);
    $this->addAdditionalFields();
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    if (empty($this->options['format_date_sql'])) {
      return parent::render($values);
    }

    return $this->getValue($values);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $values, $field = NULL) {
    $value = parent::getValue($values, $field);
    if (is_numeric($value) || !empty($this->options['format_date_sql'])) {
      return $value;
    }

    $format = isset($this->definition['type']) && $this->definition['type'] == 'datetime'
      ? DateTimeItemInterface::DATETIME_STORAGE_FORMAT
      : DateTimeItemInterface::DATE_STORAGE_FORMAT;
    return DrupalDateTime::createFromFormat($format, $value)->getTimestamp();
  }
}
