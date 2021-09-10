<?php

namespace Drupal\exec_environment\Plugin\ExecEnvironment\ImpactApplicator;

use Drupal\Core\Plugin\PluginBase;
use Drupal\exec_environment\EnvironmentInterface;
use Drupal\exec_environment\Plugin\ExecEnvironment\Component\CurrentUserComponentInterface;

/**
 * Applicator to apply the current user from the environment.
 *
 * @ExecEnvironmentImpactApplicator(
 *   id = "current_user",
 * )
 *
 * @package Drupal\exec_environment\Plugin\ExecEnvironment\ImpactApplicator
 */
class CurrentUserImpactApplicator extends PluginBase implements ImpactApplicatorInterface {

  /**
   * {@inheritdoc}
   */
  public function apply(EnvironmentInterface $environment) {
    /** @var \Drupal\exec_environment\Plugin\ExecEnvironment\Component\CurrentUserComponentInterface[] $components */
    $components = $environment->getComponents(CurrentUserComponentInterface::class);
    foreach ($components as $component) {
      if ($account = $component->getTargetCurrentUser()) {
        \Drupal::currentUser()->setAccount($account);
        return;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function reset(EnvironmentInterface $environment) {
    // Apply the previous environment's current user.
    if ($environment->previousEnvironment()) {
      /** @var \Drupal\exec_environment\Plugin\ExecEnvironment\Component\CurrentUserComponentInterface $component */
      foreach ($environment->previousEnvironment()->getComponents(CurrentUserComponentInterface::class) as $component) {
        if ($account = $component->getTargetCurrentUser()) {
          \Drupal::currentUser()->setAccount($account);
          return;
        }
      }

      // If we haven't reset the account, call reset on the previous environment.
      $this->reset($environment->previousEnvironment());
    }
  }
}
