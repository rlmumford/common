<?php

namespace Drupal\identity_ui\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\identity\IdentityDataClassManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IdentityDataClassDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * @var string
   */
  protected $basePluginId;

  /**
   * @var \Drupal\identity\IdentityDataClassManager
   */
  protected $identityDataClassManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('plugin.manager.identity_data_class')
    );
  }

  /**
   * IdentityDataClassDeriver constructor.
   *
   * @param $base_plugin_id
   * @param \Drupal\identity\IdentityDataClassManager $identity_data_class_manager
   */
  public function __construct($base_plugin_id, IdentityDataClassManager $identity_data_class_manager) {
    $this->basePluginId = $base_plugin_id;
    $this->identityDataClassManager = $identity_data_class_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];

    foreach ($this->identityDataClassManager->getDefinitions() as $class => $definition) {
      $this->derivatives[$class] = [
        'label' => new TranslatableMarkup(
          'Identity @class_plural',
          [
            '@class_plural' => !empty($definition['plural_label']) ? $definition['plural_label'] : ($definition['label'].'s'),
          ]
        ),
        'identity_data_class' => $class,
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }
}
