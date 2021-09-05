<?php


namespace Drupal\exec_environment;

use Drupal\exec_environment\Plugin\ExecEnvironment\Component\ComponentInterface;

/**
 * The Environment Object.
 */
class Environment implements EnvironmentInterface {

  /**
   * The previous environment.
   *
   * @var \Drupal\exec_environment\EnvironmentInterface|null
   */
  protected $previousEnvironment;

  /**
   * The environment components.
   *
   * @var \Drupal\exec_environment\Plugin\ExecEnvironment\Component\ComponentInterface[]
   */
  protected $components;

  /**
   * {@inheritdoc}
   */
  public function previousEnvironment(): ?EnvironmentInterface {
    return $this->previousEnvironment;
  }

  /**
   * {@inheritdoc}
   */
  public function setPreviousEnvironment(EnvironmentInterface $environment): EnvironmentInterface {
    $this->previousEnvironment = $environment;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addComponent(ComponentInterface $component) : EnvironmentInterface {
    $this->components[] = $component;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getComponents(string $impact_interface = NULL) : array {
    $result = $this->components;
    if ($impact_interface) {
      $result = array_filter(
        $result,
        function ($component) use ($impact_interface) {
          return is_subclass_of($component, $impact_interface);
        }
      );
    }
    usort($result, function ($a, $b) {
      $a_priority = $a->getPriority() ?: 0;
      $b_priority = $b->getPriority() ?: 0;


      if ($a_priority === $b_priority) {
        return 0;
      }

      return $a_priority < $b_priority ? 1 : -1;
    });
    return $result;
  }

}
