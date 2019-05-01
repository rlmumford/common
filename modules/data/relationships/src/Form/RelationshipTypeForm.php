<?php

namespace Drupal\relationships\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;

class RelationshipTypeForm extends BundleEntityFormBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\relationships\Entity\RelationshipType $entity */
    $entity = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $entity->label,
      '#size' => 30,
      '#required' => TRUE,
      '#maxlength' => 64,
      '#description' => $this->t('The name of this relationship type.'),
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#required' => TRUE,
      '#disabled' => !$entity->isNew(),
      '#size' => 30,
      '#maxlength' => 64,
      '#machine_name' => [
        'exists' => [
          '\\Drupal\\relationships\\Entity\\RelationshipType',
          'load',
        ],
      ],
    ];

    $form['forward_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Forward Label'),
      '#description' => $this->t('Label that describes the relationship from tail to head, e.g. "is employed by"'),
      '#default_value' => $entity->forward_label,
      '#required' => TRUE,
    ];
    $form['backward_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Backward Label'),
      '#description' => $this->t('Label that describes the relationship form head to tail, e.g "employs"'),
      '#default_value' => $entity->backward_label,
      '#required' => TRUE,
    ];

    $form['tail'] = $this->endConfigForm($form, $form_state, 'tail');
    $form['head'] = $this->endConfigForm($form, $form_state, 'head');

    return parent::form($form, $form_state);
  }

  public function endConfigForm($form, FormStateInterface $form_state, $end) {
    if (!$form_state->get(['settings', $end])) {
      $form_state->set(['settings', $end], [
        'entity_type_id' => $this->entity->{$end."_entity_type_id"},
        'label' => $this->entity->{$end."_label"},
        'handler' => $this->entity->{$end."_handler"} ?: 'default:'.$this->entity->{$end."_entity_type_id"},
        'handler_settings' => $this->entity->{$end."_handler_settings"} ?: [],
        'field' => $this->entity->{$end."_field"},
        'field_label' => $this->entity->{$end."_field"},
      ]);
    }

    $settings = $form_state->get(['settings', $end]);
    $handler_wrapper = $end.'-handler-wrapper';

    $element = [
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => TRUE,
      '#title' => new TranslatableMarkup('@end Settings', ['@end' => ucfirst($end)]),
    ];
    // Entity type options
    $entity_type_options = [];
    foreach (\Drupal::entityTypeManager()->getDefinitions() as $id => $entity_type) {
      $entity_type_options[$id] = $entity_type->getLabel();
    }
    $element['entity_type_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity Type'),
      '#options' => $entity_type_options,
      '#default_value' => $settings['entity_type_id'],
      '#required' => TRUE,
      '#ajax' => [
        'wrapper' => $handler_wrapper,
        'callback' => [static::class, 'formAjaxReloadHandler'],
        'trigger_as' => ['name' => $end.'_entity_type_id_submit'],
      ],
    ];
    $element['entity_type_id_submit'] = [
      '#type' => 'submit',
      '#title' => new TranslatableMarkup('Change Entity Type'),
      '#limit_validation_errors' => [
        [$end, 'entity_type_id'],
      ],
      '#name' => $end.'_entity_type_id_submit',
      '#end' => $end,
      '#attributes' => [
        'class' => ['js-hide'],
      ],
      '#submit' => [[static::class, 'formSubmitChangeEntityTypeId']],
      '#ajax' => [
        'wrapper' => $handler_wrapper,
        'callback' => [static::class, 'formAjaxReloadHandler'],
      ],
    ];
    $element['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#description' => $this->t('A better label for the @end', ['@end' => $end]),
      '#default_value' => $settings['label'],
      '#required' => TRUE,
    ];

    if ($settings['entity_type_id']) {
      // Get all selection plugins for this entity type.
      $selection_plugins = \Drupal::service('plugin.manager.entity_reference_selection')->getSelectionGroups($settings['entity_type_id']);
      $handlers_options = [];
      foreach (array_keys($selection_plugins) as $selection_group_id) {
        // We only display base plugins (e.g. 'default', 'views', ...) and not
        // entity type specific plugins (e.g. 'default:node', 'default:user',
        // ...).
        if (array_key_exists($selection_group_id, $selection_plugins[$selection_group_id])) {
          $handlers_options[$selection_group_id] = Html::escape($selection_plugins[$selection_group_id][$selection_group_id]['label']);
        }
        elseif (array_key_exists($selection_group_id . ':' . $settings['entity_type_id'], $selection_plugins[$selection_group_id])) {
          $selection_group_plugin = $selection_group_id . ':' . $settings['entity_type_id'];
          $handlers_options[$selection_group_plugin] = Html::escape($selection_plugins[$selection_group_id][$selection_group_plugin]['base_plugin_label']);
        }
      }

      $element['handler'] = [
        '#type' => 'details',
        '#title' => t('Reference type'),
        '#open' => TRUE,
        '#tree' => TRUE,
        '#end' => $end,
        '#handler_wrapper_id' => $handler_wrapper,
        '#prefix' => '<div id="' . $handler_wrapper . '">',
        '#suffix' => '</div>',
        '#process' => [[static::class, 'formExpandHandlerSettingsAjax']],
      ];
      $element['handler']['handler'] = [
        '#type' => 'select',
        '#title' => t('Reference method'),
        '#options' => $handlers_options,
        '#default_value' => $settings['handler'],
        '#required' => TRUE,
        '#ajax' => [
          'wrapper' => $handler_wrapper,
          'callback' => [static::class, 'formAjaxReloadHandler'],
          'trigger_as' => ['name' => $end . '_handler_submit'],
        ],
        '#limit_validation_errors' => [],
      ];
      $element['handler']['handler_submit'] = [
        '#type' => 'submit',
        '#value' => t('Change handler'),
        '#limit_validation_errors' => [
          [$end, 'handler', 'handler'],
        ],
        '#name' => $end . '_handler_submit',
        '#end' => $end,
        '#attributes' => [
          'class' => ['js-hide'],
        ],
        '#submit' => [[static::class, 'formSubmitChangeHandler']],
        '#ajax' => [
          'wrapper' => $handler_wrapper,
          'callback' => [static::class, 'formAjaxReloadHandler'],
        ],
      ];

      $element['handler']['handler_settings'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['entity_reference-settings']],
      ];

      $options = $settings['handler_settings'];
      $options += [
        'target_type' => $settings['entity_type_id'],
        'handler' => $settings['handler'],
      ];
      /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginBase $handler */
      $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getInstance($options);
      $element['handler']['handler_settings'] += $handler->buildConfigurationForm([], $form_state);

      // @todo: This is a hack in lieu of core playing properly with #parents.
      if (!empty($element['handler']['handler_settings']['auto_create_bundles'])) {
        $element['handler']['handler_settings']['auto_create_bundles']['#states']['visible'] = [
          ':input[name="'.$end.'[handler][handler_settings][auto_create]"]' => ['checked' => TRUE],
        ];
      }
      if (!empty($element['handler']['handler_settings']['auto_create_roles'])) {
        $element['handler']['handler_settings']['auto_create_roles']['#states']['visible'] = [
          ':input[name="'.$end.'[handler][handler_settings][auto_create]"]' => ['checked' => TRUE],
        ];
      }
    }
    else {
      $element['handler'] = [
        '#type' => 'container',
        '#prefix' => '<div id="' . $handler_wrapper . '">',
        '#suffix' => '</div>',
      ];
    }

    return $element;
  }

  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    parent::copyFormValuesToEntity($entity, $form, $form_state);

    foreach (['tail', 'head'] as $end) {
      foreach ($form_state->getValue($end) as $end_property => $end_value) {
        if ($end_property == 'handler') {
          if ($entity_type_id = $form_state->getValue($end)['entity_type_id']) {
            $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
            if (!$entity_type->hasKey('bundle')) {
              unset($end_value['handler_settings']['target_bundles']);
            }
          }

          $entity->set($end.'_handler', $end_value['handler']);
          $entity->set($end.'_handler_settings', $end_value['handler_settings']);
        }
        else {
          $entity->set($end . '_' . $end_property, $end_value);
        }
      }
    }
  }

  public static function formExpandHandlerSettingsAjax($form, FormStateInterface $form_state) {
    static::formElementExpandAjax($form, $form);
    return $form;
  }

  public static function formElementExpandAjax(&$element, $main_section) {
    if (!empty($element['#ajax']) && !is_array($element['#ajax'])) {
      $element['#end'] = $main_section['#end'];
      $element['#ajax'] = [
        'callback' => [static::class, 'formAjaxReloadHandler'],
        'wrapper' => $main_section['#handler_wrapper_id'],
      ];
    }

    foreach (Element::children($element) as $key) {
      static::formElementExpandAjax($element[$key], $main_section);
    }
  }

  public static function formSubmitChangeHandler($form, FormStateInterface $form_state) {
    $element = $form_state->getTriggeringElement();

    $parents = $element['#parents'];
    array_pop($parents); array_push($parents, 'handler');

    $settings = &$form_state->get(['settings', $element['#end']]);
    $settings['handler'] = $form_state->getValue($parents);

    $form_state->setRebuild(TRUE);
  }

  public static function formSubmitChangeEntityTypeId($form, FormStateInterface $form_state) {
    $element = $form_state->getTriggeringElement();

    $parents = $element['#parents'];
    array_pop($parents); array_push($parents, 'entity_type_id');

    $settings = &$form_state->get(['settings', $element['#end']]);
    $settings['entity_type_id'] = $form_state->getValue($parents);
    $settings['handler'] = 'default:'.$settings['entity_type_id'];
    $settings['handler_settings'] = [];

    $form_state->setRebuild(TRUE);
  }

  public static function formAjaxReloadHandler($form, FormStateInterface $form_state) {
    $end = $form_state->getTriggeringElement()['#end'];
    return $form[$end]['handler'];
  }
}
