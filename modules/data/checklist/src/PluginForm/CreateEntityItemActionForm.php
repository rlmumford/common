<?php

namespace Drupal\checklist\PluginForm;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Action form for creating an entity.
 */
class CreateEntityItemActionForm extends PluginFormBase implements ContainerInjectionInterface {

  /**
   * The checklist item handler.
   *
   * @var \Drupal\checklist\Plugin\ChecklistItemHandler\CreateEntity
   */
  protected $plugin;

  /**
   * The bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * CreateEntityItemActionForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info
   *   The bundle info service.
   */
  public function __construct(EntityTypeBundleInfoInterface $bundle_info) {
    $this->bundleInfo = $bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $item = $this->plugin->getItem();
    $wrapper_id = "checklist-action--" . $item->checklist->checklist->getKey() . "--" . $item->getName();
    $form['#prefix'] = '<div id="' . $wrapper_id . '" class="checklist-item-action-form-wrapper">';
    $form['#suffix'] = '</div>';

    $bundle = $form_state->get('bundle') ?: $this->plugin->getConfiguration()['bundle'];
    $bundle_options = [];
    foreach ($this->bundleInfo->getBundleInfo($this->plugin->getEntityType()->id()) as $bundle_name => $info) {
      $bundle_options[$bundle_name] = $info['label'];
    }

    if (count($bundle_options) === 1) {
      $bundle = key($bundle_options);
    }

    if ($bundle !== '__select') {
      $entity = $this->plugin->doCreateEntity($bundle);

      $form['entity'] = [
        '#type' => 'inline_entity_form',
        '#entity_type' => $entity->getEntityTypeId(),
        '#bundle' => $entity->bundle(),
        '#form_mode' => $this->plugin->getConfiguration()['form_mode'],
        '#default_value' => $entity,
      ];
    }
    else {
      $form['bundle'] = [
        '#type' => 'select',
      // @todo Replace bundle wording with correct field name.
        '#title' => new TranslatableMarkup('Bundle'),
        '#options' => $bundle_options,
        '#ajax' => [
          'callback' => [static::class, 'ajaxRebuildForm'],
          'trigger_as' => ['name' => 'bundle_select_rebuild'],
          'wrapper' => $wrapper_id,
        ],
      ];
      $form['bundle_select_rebuild'] = [
        '#type' => 'submit',
        '#value' => new TranslatableMarkup('Select'),
        '#name' => 'bundle_select_rebuild',
        '#submit' => [
          [static::class, 'submitConfigurationFormSelectBundle'],
        ],
        '#ajax' => [
          'callback' => [static::class, 'ajaxRebuildForm'],
          'wrapper' => $wrapper_id,
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $form['entity']['#entity'];
    $entity->save();

    $item = $this->plugin->getItem();
    $item->setComplete(ChecklistItemInterface::METHOD_INTERACTIVE);
    $item->setOutcome($entity->getEntityTypeId(), $entity);
    $item->save();
  }

  /**
   * Reload the form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form to be rendered.
   */
  public static function ajaxRebuildForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Submit the configuration form to select the bundle.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function submitConfigurationFormSelectBundle(array $form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#parents'];
    array_pop($parents);
    $parents[] = 'bundle';

    $bundle = $form_state->getValue($parents);
    $form_state->set('bundle', $bundle);

    $form_state->setRebuild();
  }

}
