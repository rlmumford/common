<?php

namespace Drupal\checklist\Form;

use Drupal\checklist\ChecklistInterface;
use Drupal\checklist\ChecklistTempstoreRepository;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\BaseFormIdInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class ChecklistRowForm extends ChecklistItemFormBase {

  /**
   * @var string
   */
  protected $formClass = 'row';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ci_'.$this->item->checklist->checklist->getKey().'__'.$this->item->getName().'_row_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    return 'ci_row_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $wrapper_id = "checklist-row--".$this->item->checklist->checklist->getKey()."--".$this->item->getName();
    $form['#prefix'] = '<div id="'.$wrapper_id.'" class="checklist-item-row-form-wrapper">';
    $form['#suffix'] = '</div>';
    $form['#wrapper_id'] = $wrapper_id;

    return parent::buildForm($form, $form_state); // TODO: Change the autogenerated stub
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array|AjaxResponse
   */
  public static function onCompleteAjaxCallback(array &$form, FormStateInterface $form_state) {
    if (!$form_state->isExecuted()) {
      return $form;
    }

    $response = static::prepareAjaxResponse($form, $form_state);

    // @todo: Reload any dependent forms.
    // @todo: Close any resource or form panes.

    return $response;
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array|\Drupal\Core\Ajax\AjaxResponse
   */
  public static function onReverseAjaxCallback(array &$form, FormStateInterface $form_state) {
    if (!$form_state->isExecuted()) {
      return $form;
    }

    $response = static::prepareAjaxResponse($form, $form_state);

    // @todo: Reload any dependent forms.
    // @todo: Close any resource or form panes.

    return $response;
  }

  protected static function ajaxInsertForm(AjaxResponse $response, $form_arg, $selector = NULL) {
    $form = \Drupal::formBuilder()->getForm($form_arg);

    $html = \Drupal::service('renderer')->renderRoot($form);
    if (
      $selector && strpos($selector, '#') === 1 &&
      strpos($selector, '.') === FALSE && stripos($selector, ' ') === FALSE
    ) {
      $id = substr($selector, 1);
      $html = '<div id="'.$id.'">'.$html.'</div>';
    }
    $response->addAttachments($form['#attached']);
    $response->addCommand(new InsertCommand($selector, $html));
  }

  protected static function prepareAjaxResponse(array &$form, FormStateInterface $form_state) : AjaxResponse {
    /** @var \Drupal\Core\Render\MainContent\MainContentRendererInterface $ajax_renderer */
    $ajax_renderer = \Drupal::service('main_content_renderer.ajax');

    // If the form is rebuilding then we need to still render it, but we have to
    // do it directly so that we can add more to the commands list.
    if ($form_state->isRebuilding()) {
      $response = $ajax_renderer->renderResponse($form, \Drupal::request(), \Drupal::routeMatch());
    }
    else {
      $response = new AjaxResponse();
    }

    return $response;
  }


}