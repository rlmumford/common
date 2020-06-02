<?php
/**
 * Created by PhpStorm.
 * User: Rob
 * Date: 28/05/2020
 * Time: 14:52
 */

namespace Drupal\checklist\PluginForm;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CreateEntityItemActionForm extends PluginFormBase implements ContainerInjectionInterface {

  /**
   * @var \Drupal\checklist\Plugin\ChecklistItemHandler\CreateEntity
   */
  protected $plugin;

  /**
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info')
    );
  }

  public function __construct(EntityTypeBundleInfoInterface $bundle_info) {
    $this->bundleInfo = $bundle_info;
  }

  /**
   * Form constructor.
   *
   * Plugin forms are embedded in other forms. In order to know where the plugin
   * form is located in the parent form, #parents and #array_parents must be
   * known, but these are not available during the initial build phase. In order
   * to have these properties available when building the plugin form's
   * elements, let this method return a form element that has a #process
   * callback and build the rest of the form in the callback. By the time the
   * callback is executed, the element's #parents and #array_parents properties
   * will have been set by the form API. For more documentation on #parents and
   * #array_parents, see \Drupal\Core\Render\Element\FormElement.
   *
   * @param array $form
   *   An associative array containing the initial structure of the plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   *
   * @return array
   *   The form structure.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $item = $this->plugin->getItem();
    $wrapper_id = "checklist-action--".$item->checklist->checklist->getKey()."--".$item->getName();
    $form['#prefix'] = '<div id="'.$wrapper_id.'" class="checklist-item-action-form-wrapper">';
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
        '#title' => new TranslatableMarkup('Bundle'), // @todo: Replace bundle wording with correct field name.
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
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the plugin form as built
   *   by static::buildConfigurationForm().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $entity = $form['entity']['#entity'];
    $entity->save();

    // @todo: Outcomes.

    $item = $this->plugin->getItem();
    $item->setComplete(ChecklistItemInterface::METHOD_INTERACTIVE);
    $item->save();
  }

  /**
   * Reload the form.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public static function ajaxRebuildForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  public static function submitConfigurationFormSelectBundle(array $form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#parents'];
    array_pop($parents); $parents[] = 'bundle';

    $bundle = $form_state->getValue($parents);
    $form_state->set('bundle', $bundle);

    $form_state->setRebuild();
  }
}
