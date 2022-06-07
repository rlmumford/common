<?php

namespace Drupal\checklist\PluginForm;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormBase;

/**
 * Action form for update entity item handlers.
 */
class UpdateEntityItemActionForm extends PluginFormBase {

  /**
   * The item handler.
   *
   * @var \Drupal\checklist\Plugin\ChecklistItemHandler\UpdateEntity
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $entity = $this->plugin->getContextValue("entity");

    $form['entity'] = [
      '#type' => 'inline_entity_form',
      '#entity_type' => $entity->getEntityTypeId(),
      '#bundle' => $entity->bundle(),
      '#form_mode' => $this->plugin->getConfiguration()['form_mode'],
      '#default_value' => $entity,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $entity = $form['entity']['#entity'];
    $entity->save();

    $item = $this->plugin->getItem();
    $item->setComplete(ChecklistItemInterface::METHOD_INTERACTIVE);
    $item->save();
  }

}
