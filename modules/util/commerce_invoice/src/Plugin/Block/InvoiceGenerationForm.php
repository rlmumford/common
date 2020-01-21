<?php

namespace Drupal\commerce_invoice\Plugin\Block;

use Drupal\commerce_invoice\Form\InvoiceGenerateForm;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class InvoiceGenerationForm
 *
 * @Block(
 *   id = "invoice_generation_form",
 *   admin_label = @Translation("Invoice Generation Form"),
 *   context = {
 *     "order" = @ContextDefinition("entity:commerce_order", label = @Translation("Order"))
 *   }
 * )
 *
 * @package Drupal\commerce_invoice\Plugin\Block
 */
class InvoiceGenerationForm extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder')
    );
  }

  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    try {
      return $this->formBuilder->getForm(
        InvoiceGenerateForm::class,
        $this->getContextValue('order')
      );
    }
    catch (PluginException $exception) {
      return [];
    }
  }
}
