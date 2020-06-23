<?php

namespace Drupal\rlm_fields\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\LanguageSelectWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rlm_fields\Plugin\Field\FieldType\SpokenLanguageItem;

/**
 * Plugin implementation of the 'Spoken Language' widget.
 *
 * @FieldWidget(
 *   id = "spoken_language_select",
 *   label = @Translation("Spoken language select"),
 *   field_types = {
 *     "spoken_language"
 *   }
 * )
 */
class SpokenLanguageSelect extends LanguageSelectWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['value']['#title'] = $this->t('Language');
    $element['value']['#title_display'] = 'before';
    $element['proficiency'] = [
      '#type' => 'select',
      '#title' => $this->t('Proficiency'),
      '#empty_option' => $this->t('None'),
      '#options' => [
        SpokenLanguageItem::PROF_PART => $this->t('Partial'),
        SpokenLanguageItem::PROF_FLUENT => $this->t('Fluent'),
        SpokenLanguageItem::PROF_MOTHER => $this->t('Mother Tongue'),
      ],
      '#default_value' => $items[$delta]->proficiency,
    ];

    return $element;
  }

}
