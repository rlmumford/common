<?php

namespace Drupal\commerce_invoice\Form;

use DateInterval;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class InvoiceGenerateForm extends FormBase {

  /**
   * The commerce order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * InvoiceGenerateForm constructor.
   */
  public function __construct() {
  }

  /**
   * Returns a unique string identifying the form.
   *
   * The returned ID should be a unique string that can be a valid PHP function
   * name, since it's used in hook implementation names such as
   * hook_form_FORM_ID_alter().
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'commerce_invoice_generate_form';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, OrderInterface $order = NULL) {
    $this->order = $order;

    if ($order->getState()->getString()==='draft') {
      $form['placeholder'] = [
        '#markup' => t('Cannot generate invoice until order has been placed.')
      ];
      return $form;
    }
    $placed_date = DrupalDateTime::createFromTimestamp($order->getPlacedTime() ?? time());
    if ($this->order->get('payment_due_date')->isEmpty()){
      $due_date = $placed_date->add(new DateInterval('P1D'));
    }
    else {
      $due_date = DrupalDateTime::createFromFormat('Y-m-d',$this->order->get('payment_due_date')->value);
    }

    $form['due_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Payment Due Date'),
      '#default_value' =>  $due_date->format('Y-m-d'),
      '#required' => TRUE,
    ];
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate'),
      '#submit' => [
        '::submitForm',
        '::downloadInvoice',
      ]
    ];

    if (!$this->order->invoice_pdf->isEmpty()) {
      $form['actions']['submit']['#value'] = $this->t('Re-generate');

      $form['current_invoice'] = $this->order->invoice_pdf->view([
        'type' => 'file_default',
        'label' => 'hidden',
      ]);
    }

    return $form;
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->order->payment_due_date = $form_state->getValue('due_date');

    $file = \Drupal::service('commerce_invoice.order_invoice_generator')->generatePDF($this->order);

    $form_state->set('file', $file);
  }

  /**
   *
   */
  public function downloadInvoice(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\file\Entity\File $file */
    if ($file = $form_state->get('file')) {
      $form_state->setResponse(new BinaryFileResponse(
        $file->getFileUri(),
        200,
        [],
        TRUE,
        ResponseHeaderBag::DISPOSITION_ATTACHMENT
      ));
    }
  }
}
