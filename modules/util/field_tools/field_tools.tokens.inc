<?php

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;

/**
 * Implements hook_tokens_info_alter().
 *
 * For some reason, the tokens module doesn't handle computed fields. We copy
 * their work but apply to all computed base fields.
 *
 * @see field_token_info_alter()
 */
function field_tools_token_info_alter(&$info) {
  if (!\Drupal::moduleHandler()->moduleExists('token')) {
    return;
  }

  $type_info = \Drupal::service('plugin.manager.field.field_type')->getDefinitions();

  // Attach field tokens to their respecitve entity tokens.
  foreach (\Drupal::entityTypeManager()->getDefinitions() as $entity_type_id => $entity_type) {
    if (!$entity_type->isSubclassOf('\Drupal\Core\Entity\ContentEntityInterface')) {
      continue;
    }

    // Make sure a token type exists for this entity.
    $token_type = \Drupal::service('token.entity_mapper')->getTokenTypeForEntityType($entity_type_id);
    if (empty($token_type) || !isset($info['types'][$token_type])) {
      continue;
    }

    $fields = \Drupal::service('entity_field.manager')->getBaseFieldDefinitions($entity_type_id);
    foreach ($fields as $field_name => $field) {
      /** @var BaseFieldDefinition $field */

      //  If the field isn't computed or already has a token we don't care.
      if (!$field->isComputed() || isset($info['tokens'][$token_type][$field_name])) {
        continue;
      }

      // Do not define the token type if the field has no properties.
      if (!$field->getPropertyDefinitions()) {
        continue;
      }

      // Generate a description for the token.
      $labels = _token_field_label($entity_type_id, $field_name);
      $label = array_shift($labels);
      $params['@type'] = $type_info[$field->getType()]['label'];
      if (!empty($labels)) {
        $params['%labels'] = implode(', ', $labels);
        $description = t('@type field. Also known as %labels.', $params);
      }
      else {
        $description = t('@type field.', $params);
      }

      $cardinality = $field->getCardinality();
      $cardinality = ($cardinality == BaseFieldDefinition::CARDINALITY_UNLIMITED || $cardinality > 3) ? 3 : $cardinality;
      $field_token_name = $token_type . '-' . $field_name;
      $info['tokens'][$token_type][$field_name] = [
        'name' => Html::escape($label),
        'description' => $description,
        'module' => 'token',
        // For multivalue fields the field token is a list type.
        'type' => $cardinality > 1 ? "list<$field_token_name>" : $field_token_name,
      ];

      // Field token type.
      $info['types'][$field_token_name] = [
        'name' => Html::escape($label),
        'description' => t('@label tokens.', ['@label' => Html::escape($label)]),
        'needs-data' => $field_token_name,
        'nested' => TRUE,
      ];
      // Field list token type.
      if ($cardinality > 1) {
        $info['types']["list<$field_token_name>"] = [
          'name' => t('List of @type values', ['@type' => Html::escape($label)]),
          'description' => t('Tokens for lists of @type values.', ['@type' => Html::escape($label)]),
          'needs-data' => "list<$field_token_name>",
          'nested' => TRUE,
        ];
      }

      // Show a different token for each field delta.
      if ($cardinality > 1) {
        for ($delta = 0; $delta < $cardinality; $delta++) {
          $info['tokens']["list<$field_token_name>"][$delta] = [
            'name' => t('@type type with delta @delta', ['@type' => Html::escape($label), '@delta' => $delta]),
            'module' => 'token',
            'type' => $field_token_name,
          ];
        }
      }

      // Property tokens.
      foreach ($field->getPropertyDefinitions() as $property => $property_definition) {
        if (is_subclass_of($property_definition->getClass(), 'Drupal\Core\TypedData\PrimitiveInterface')) {
          $info['tokens'][$field_token_name][$property] = [
            'name' => $property_definition->getLabel(),
            'description' => $property_definition->getDescription(),
            'module' => 'token',
          ];
        }
        elseif (($property_definition instanceof DataReferenceDefinitionInterface) && ($property_definition->getTargetDefinition() instanceof EntityDataDefinitionInterface)) {
          $referenced_entity_type = $property_definition->getTargetDefinition()->getEntityTypeId();
          $referenced_token_type = \Drupal::service('token.entity_mapper')->getTokenTypeForEntityType($referenced_entity_type);
          $info['tokens'][$field_token_name][$property] = [
            'name' => $property_definition->getLabel(),
            'description' => $property_definition->getDescription(),
            'module' => 'token',
            'type' => $referenced_token_type,
          ];
        }
      }
      // Provide image_with_image_style tokens for image fields.
      if ($field->getType() == 'image') {
        $image_styles = image_style_options(FALSE);
        foreach ($image_styles as $style => $description) {
          $info['tokens'][$field_token_name][$style] = [
            'name' => $description,
            'description' => t('Represents the image in the given image style.'),
            'type' => 'image_with_image_style',
          ];
        }
      }
      // Provide format token for datetime fields.
      if ($field->getType() == 'datetime') {
        $info['tokens'][$field_token_name]['date'] = $info['tokens'][$field_token_name]['value'];
        $info['tokens'][$field_token_name]['date']['name'] .= ' ' . t('format');
        $info['tokens'][$field_token_name]['date']['type'] = 'date';
      }
      if ($field->getType() == 'daterange') {
        $info['tokens'][$field_token_name]['start_date'] = $info['tokens'][$field_token_name]['value'];
        $info['tokens'][$field_token_name]['start_date']['name'] .= ' ' . t('format');
        $info['tokens'][$field_token_name]['start_date']['type'] = 'date';
        $info['tokens'][$field_token_name]['end_date'] = $info['tokens'][$field_token_name]['end_value'];
        $info['tokens'][$field_token_name]['end_date']['name'] .= ' ' . t('format');
        $info['tokens'][$field_token_name]['end_date']['type'] = 'date';
      }
    }
  }
}
