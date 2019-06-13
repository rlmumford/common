<?php

namespace Drupal\rlmaws\Credentials;

use Aws\Credentials\CredentialProvider;
use Drupal\bootstrap\Utility\SortArray;
use Drupal\Core\Extension\ModuleHandlerInterface;

class ContextualCredentialProvider extends CredentialProvider {

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * ContextualCredentialProvider constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * @param array $context
   * @param array $config
   *
   * @return callable
   */
  public function contextualProvider(array $context = [], array $config = []) {
    $contextual_providers = [];

    $contextual_provider_info = $this->moduleHandler->invokeAll('rlmaws_contextual_credential_provider_info', [$context, $config]);
    $this->moduleHandler->alter('rlmaws_contextual_credential_provider_info', $contextual_provider_info, $context, $config);
    uasort($contextual_provider_info, function($a, $b) {
      return SortArray::sortByKeyInt($a, $b, 'priority');
    });
    $contextual_provider_info = array_reverse($contextual_provider_info);

    foreach ($contextual_provider_info as $provider_info) {
      $contextual_providers[] = $provider_info['provider'];
    }

    return self::memoize(
      call_user_func_array(
        'self::chain',
        array_merge($contextual_providers, [self::defaultProvider($config)])
      )
    );
  }
}