<?php

namespace Drupal\identity_communication;

use Drupal\communication\Contact\ContactMiddlewareBase;

class IdentityContactMiddleware extends ContactMiddlewareBase {

  /**
   * {@inheritdoc}
   */
  public function contactEntityTypeId() {
    return 'identity';
  }
}
