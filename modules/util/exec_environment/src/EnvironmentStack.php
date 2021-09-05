<?php

namespace Drupal\exec_environment;

/**
 * The environment stack service.
 *
 * @package Drupal\exec_environment
 */
class EnvironmentStack implements EnvironmentStackInterface {

  /**
   * The environment stack.
   *
   * @var \Drupal\exec_environment\EnvironmentInterface[]
   */
  protected $stack = [];

  /**
   * The impact applicator manager.
   *
   * @var \Drupal\exec_environment\EnvironmentImpactApplicatorManager
   */
  protected $impactApplicatorManager;

  /**
   * The component manager.
   *
   * @var \Drupal\exec_environment\EnvironmentComponentManager
   */
  protected $componentManager;

  /**
   * EnvironmentStack constructor.
   *
   * @param \Drupal\exec_environment\EnvironmentImpactApplicatorManager $impact_applicator_manager
   *   The impact applicator manager.
   * @param \Drupal\exec_environment\EnvironmentComponentManager $component_manager
   *   The
   */
  public function __construct(
    EnvironmentImpactApplicatorManager $impact_applicator_manager,
    EnvironmentComponentManager $component_manager
  ) {
    $this->impactApplicatorManager = $impact_applicator_manager;
    $this->componentManager = $component_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function applyEnvironment(EnvironmentInterface $environment) {
    $current_environment = end($this->stack) ?: $this->defaultEnvironment();
    $environment->setPreviousEnvironment($current_environment);
    array_push($this->stack, $environment);

    foreach ($this->impactApplicatorManager->getDefinitions() as $id => $definition) {
      /** @var \Drupal\exec_environment\Plugin\ExecEnvironment\ImpactApplicator\ImpactApplicatorInterface $applicator */
      $applicator = $this->impactApplicatorManager->createInstance($id);
      $applicator->apply($environment);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function resetEnvironment() {
    $current_environment = array_pop($this->stack);
    foreach ($this->impactApplicatorManager->getDefinitions() as $id => $definition) {
      /** @var \Drupal\exec_environment\Plugin\ExecEnvironment\ImpactApplicator\ImpactApplicatorInterface $applicator */
      $applicator = $this->impactApplicatorManager->createInstance($id);
      $applicator->reset($current_environment);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveEnvironment(): EnvironmentInterface {
    return end($this->stack) ?: $this->defaultEnvironment();
  }

  /**
   * Construct the default environment.
   *
   * @return \Drupal\exec_environment\EnvironmentInterface
   *   The default environment.
   *
   * @todo Make this extenable through impact applicators?
   */
  protected function defaultEnvironment() : EnvironmentInterface {
    return (new Environment())
      ->addComponent($this->componentManager->createInstance(
        'configured_current_user',
        ['user' => \Drupal::currentUser()->getAccount()]
      ));
  }
}
