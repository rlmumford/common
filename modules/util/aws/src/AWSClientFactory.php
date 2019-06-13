<?php

namespace Drupal\rlmaws;

use Aws\Credentials\CredentialProvider;
use Aws\Exception\CredentialsException;
use Aws\Sdk;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\rlmaws\Credentials\ContextualCredentialProvider;
use GuzzleHttp\Promise\RejectedPromise;

class AWSClientFactory extends Sdk {

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * @var \
   */
  protected $credentialProvider;

  /**
   * AWSClientFactory constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param array $args
   */
  public function __construct(ConfigFactoryInterface $config_factory, ContextualCredentialProvider $credential_provider, AccountInterface $current_user, array $args = []) {
    $this->credentialProvider = $credential_provider;
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;

    $args += [
      'region' => 'us-west-2',
      'version' => 'latest',
    ];

    if (!isset($args['credentials'])) {
      $args['credentials'] = $this->credentialProvider->contextualProvider([
          'current_user' => $current_user,
        ],
        $args
      );
    }

    parent::__construct($args);
  }
}