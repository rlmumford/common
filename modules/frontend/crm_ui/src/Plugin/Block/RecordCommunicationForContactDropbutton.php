<?php

namespace Drupal\rlmcrm_ui\Plugin\Block;

use Drupal\communication\CommunicationModePluginManager;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class RecordCommunicationForContactDropbutton
 *
 * @Block(
 *   id = "record_communication_for_contact_dropbutton",
 *   admin_label = @Translation("Record Communication for Contact"),
 *   context_definitions = {
 *     "contact" = @ContextDefinition("entity:user", label = @Translation("Contact"))
 *   }
 * )
 *
 * @package Drupal\rlmcrm_ui\Plugin\Block
 */
class RecordCommunicationForContactDropbutton extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\communication\CommunicationModePluginManager
   */
  protected $modeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('plugin.manager.communication.mode'));
  }

  /**
   * RecordCommunicationForContactDropbutton constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\communication\CommunicationModePluginManager $mode_manager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CommunicationModePluginManager $mode_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->modeManager = $mode_manager;
  }

  /**
   * @return array|string[]
   */
  public function getCacheContexts() {
    $cache_contexts = parent::getCacheContexts();

    $cache_contexts = Cache::mergeContexts($cache_contexts, [
      'user',
    ]);

    return $cache_contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $element = [
      '#type' => 'dropbutton',
      '#links' => [],
    ];

    $contact = $this->getContextValue('contact');
    $element['#links']['email_to'] = [
      'title' => new TranslatableMarkup('Record Email to @contact', ['@contact' => $contact->label()]),
      'url' => Url::fromRoute(
        'entity.communication.record_form',
        [
          'mode' => 'email',
        ],
        [
          'query' => [
            'participants' => [
              'to' => $contact->id(),
              'from' => \Drupal::currentUser()->id(),
            ],
          ] + \Drupal::destination()->getAsArray(),
        ]
      ),
    ];
    $element['#links']['email_from'] = [
      'title' => new TranslatableMarkup('Record Email from @contact', ['@contact' => $contact->label()]),
      'url' => Url::fromRoute(
        'entity.communication.record_form',
        [
          'mode' => 'email',
        ],
        [
          'query' => [
            'participants' => [
              'from' => $contact->id(),
              'to' => \Drupal::currentUser()->id(),
            ],
          ] + \Drupal::destination()->getAsArray(),
        ]
      ),
    ];
    $element['#links']['call_to'] = [
      'title' => new TranslatableMarkup('Record Phone Call to @contact', ['@contact' => $contact->label()]),
      'url' => Url::fromRoute(
        'entity.communication.record_form',
        [
          'mode' => 'telephone',
        ],
        [
          'query' => [
            'participants' => [
              'recipient' => $contact->id(),
              'caller' => \Drupal::currentUser()->id(),
            ],
          ] + \Drupal::destination()->getAsArray(),
        ]
      ),
    ];
    $element['#links']['call_from'] = [
      'title' => new TranslatableMarkup('Record Phone Call from @contact', ['@contact' => $contact->label()]),
      'url' => Url::fromRoute(
        'entity.communication.record_form',
        [
          'mode' => 'telephone',
        ],
        [
          'query' => [
            'participants' => [
              'caller' => $contact->id(),
              'recipient' => \Drupal::currentUser()->id(),
            ],
          ] + \Drupal::destination()->getAsArray(),
        ]
      ),
    ];

    return $element;
  }
}
