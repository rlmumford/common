<?php

namespace Drupal\place;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class PlacePermissions
 *
 * @package Drupal\place
 */
class PlacePermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * @var \Drupal\place\PlaceHandlerPluginManager|null
   */
  protected $handlerManager = NULL;

  /**
   * Instantiates a new instance of this class.
   *
   * This is a factory method that returns a new instance of this class. The
   * factory should pass any needed dependencies into the constructor of this
   * class, but not the container itself. Every call to this method must return
   * a new instance of this class; that is, it may not implement a singleton.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container this instance should use.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.place.place_handler'),
      $container->get('string_translation')
    );
  }

  /**
   * PlacePermissions constructor.
   *
   * @param \Drupal\place\PlaceHandlerPluginManager $handler_manager
   */
  public function __construct(PlaceHandlerPluginManager $handler_manager, TranslationInterface $string_translation) {
    $this->handlerManager = $handler_manager;
    $this->stringTranslation = $string_translation;
  }

  /**
   * Get permissions for place entities
   */
  public function permissions() {
    $permissions = [];

    foreach ($this->handlerManager->getDefinitions() as $id => $info) {
      $params = [
        '%type' => $info['label'],
      ];

      $permissions += [
        "create $id places" => [
          'title' => $this->t('Create new %type places', $params),
        ],
        "edit own $id places" => [
          'title' => $this->t('Edit own %type places', $params),
        ],
        "edit any $id places" => [
          'title' => $this->t('Edit any %type places', $params),
        ],
        "delete own $id places" => [
          'title' => $this->t('Delete own %type places', $params),
        ],
        "delete any $id places" => [
          'title' => $this->t('Delete any %type places', $params),
        ],
      ];
    }

    return $permissions;
  }
}
