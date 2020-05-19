<?php

namespace Drupal\rlm_material\EventSubscriber;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\social_media\Event\SocialMediaEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SocialMediaEventSubscriber implements EventSubscriberInterface {

  /**
   * Returns an array of event names this subscriber wants to listen to.
   *
   * The array keys are event names and the value can be:
   *
   *  * The method name to call (priority defaults to 0)
   *  * An array composed of the method name to call and the priority
   *  * An array of arrays composed of the method names to call and respective
   *    priorities, or 0 if unset
   *
   * For instance:
   *
   *  * array('eventName' => 'methodName')
   *  * array('eventName' => array('methodName', $priority))
   *  * array('eventName' => array(array('methodName1', $priority), array('methodName2')))
   *
   * @return array The event names to listen to
   */
  public static function getSubscribedEvents() {
    $events = [
      'social_media.pre_render' => ['onPreRender', 0],
    ];
    return $events;
  }

  public function onPreRender(SocialMediaEvent $event) {
    $elements = $event->getElement();
    $elements['facebook_share']['img'] = NULL;
    $elements['facebook_share']['text'] = new TranslatableMarkup('<i class="services-icons icon-primary" data-icon="facebook"></i>');

    $elements['linkedin']['img'] = NULL;
    $elements['linkedin']['text'] = new TranslatableMarkup('<i class="services-icons icon-primary" data-icon="linkedin"></i>');

    $elements['twitter']['img'] = NULL;
    $elements['twitter']['text'] = new TranslatableMarkup('<i class="services-icons icon-primary" data-icon="twitter"></i>');

    $event->setElement($elements);
  }
}
