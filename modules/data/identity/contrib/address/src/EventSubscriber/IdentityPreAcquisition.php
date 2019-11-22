<?php

namespace Drupal\identity_address_data\EventSubscriber;

use Drupal\identity\Event\IdentityEvents;
use Drupal\identity\Event\PreAcquisitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class IdentityPreAcquisition implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      IdentityEvents::PRE_ACQUISITION => 'onPreAcquisition',
    ];
  }

  /**
   * Prepare data before acquisition.
   *
   * If there is any name data we append this too any address to improve
   * acquisition possibility.
   *
   * @param \Drupal\identity\Event\PreAcquisitionEvent $event
   */
  public function onPreAcquisition(PreAcquisitionEvent $event) {
    $data_group = $event->getIdentityDataGroup();

    $personal_name_datas = $data_group->getDatas('personal_name');
    $org_name_datas = $data_group->getDatas('organization_name');

    if (empty($personal_name_datas) && empty($org_name_datas)) {
      return;
    }

    foreach ($data_group->getDatas("address") as $address_data) {
      if (!$address_data->address->organization && ($org_name_data = reset($org_name_datas))) {
        $address_data->address->organization = $org_name_data->org_name->value;
      }

      $personal_name_data = reset($personal_name_datas);
      if (!empty($personal_name_data->name->given) && !empty($personal_name_data->name->family)) {
        $address_data->address->given_name = $personal_name_data->name->given;
        $address_data->address->family_name = $personal_name_data->name->family;
      }
    }
  }

}
