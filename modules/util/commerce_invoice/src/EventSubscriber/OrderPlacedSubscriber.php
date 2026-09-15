<?php

namespace Drupal\commerce_invoice\EventSubscriber;

use Drupal\commerce_invoice\OrderInvoiceGenerator;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Sends an email when the order transitions to Fulfillment.
*/
class OrderPlacedSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The invoice PDF generator.
   *
   * @var \Drupal\commerce_invoice\OrderInvoiceGenerator
   */
  protected OrderInvoiceGenerator $invoice_pdf_generator;

  /**
   * Constructs a new OrderPlacedSubscriber object.
   *
   * @param \Drupal\commerce_invoice\OrderInvoiceGenerator $invoice_pdf_generator
   *   The invoice PDF generator.
   */
  public function __construct(OrderInvoiceGenerator $invoice_pdf_generator) {
    $this->invoice_pdf_generator = $invoice_pdf_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'commerce_order.place.pre_transition' => ['generatePDF', -100],
    ];
  }

  /**
   * Generates the Invoice PDF.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function generatePDF(WorkflowTransitionEvent $event) {
    // Generate Order Invoice PDF.
    $order = $event->getEntity();
    $this->invoice_pdf_generator->generatePDF($order, FALSE);
  }
}
