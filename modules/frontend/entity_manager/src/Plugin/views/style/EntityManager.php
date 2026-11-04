<?php

namespace Drupal\entity_manager\Plugin\views\style;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsStyle;
use Drupal\views\Plugin\views\style\Table;

/**
 * Keeps the standard Views table and adds an on-demand entity reader.
 */
#[ViewsStyle(
  id: 'entity_manager',
  title: new TranslatableMarkup('Entity Manager'),
  help: new TranslatableMarkup('Select a table row to read its entity alongside the list.'),
  theme: 'views_view_table',
  display_types: ['normal'],
)]
final class EntityManager extends Table {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    return parent::defineOptions() + ['view_mode' => ['default' => 'default']];
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['view_mode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Preview view mode'),
      '#description' => $this->t('The configured entity view mode, for example default, full or a dedicated reader display. Entity and field access still apply.'),
      '#default_value' => $this->options['view_mode'],
      '#required' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      '#theme' => 'entity_manager',
      '#table' => parent::render(),
      '#reader_id' => Html::getUniqueId('entity-manager-reader'),
      '#attached' => ['library' => ['entity_manager/reader']],
    ];
  }

}
