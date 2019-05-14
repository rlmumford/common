<?php

namespace Drupal\rlmcrm\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Class TelephoneNumbersParagraphFormatter
 *
 * @FieldFormatter(
 *   id = "email_addresses_paragraph",
 *   label = @Translation("Email Addresses Paragraph"),
 *   field_types = {
 *     "entity_reference_revisions",
 *   },
 * )
 *
 * @package Drupal\rlmcrm\Plugin\Field\FieldFormatter
 */
class EmailAddressesParagraphFormatter extends FormatterBase {

  /**
   * Builds a renderable array for a field value.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values to be rendered.
   * @param string $langcode
   *   The language that should be used to render the field.
   *
   * @return array
   *   A renderable array for $items, as an array of child elements keyed by
   *   consecutive numeric indexes starting from 0.
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $build[0] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => [],
    ];

    foreach ($items as $item) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $para */
      $para = $item->entity;

      if ($para->bundle() == 'email_address') {
        $build[0]['#items'][] = $para->email_address->value . " (" . ucfirst($para->email_type->value) . ")";
      }
    }

    return $build;
  }
}
