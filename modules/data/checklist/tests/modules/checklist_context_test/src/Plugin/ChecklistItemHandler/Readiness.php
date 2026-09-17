<?php

namespace Drupal\checklist_context_test\Plugin\ChecklistItemHandler;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerBase;
use Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface;

/**
 * Exercises applicability, requiredness and action results.
 *
 * @ChecklistItemHandler(
 *   id = "completion_readiness_test",
 *   label = @Translation("Completion readiness test")
 * )
 */
class Readiness extends ChecklistItemHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'applicable' => TRUE,
      'required' => TRUE,
      'actionable' => TRUE,
      'method' => ChecklistItemInterface::METHOD_AUTO,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(): ?bool {
    $applicability = \Drupal::state()->get('checklist_completion_test.applicability', []);
    return array_key_exists($this->getName(), $applicability) ? $applicability[$this->getName()] : $this->getConfiguration()['applicable'];
  }

  /**
   * {@inheritdoc}
   */
  public function isRequired(): bool {
    return $this->getConfiguration()['required'];
  }

  /**
   * {@inheritdoc}
   */
  public function isActionable(): bool {
    return $this->getConfiguration()['actionable'];
  }

  /**
   * {@inheritdoc}
   */
  public function getMethod(): string {
    return $this->getConfiguration()['method'];
  }

  /**
   * {@inheritdoc}
   */
  public function action(): ChecklistItemHandlerInterface {
    $state = \Drupal::state();
    $runs = $state->get('checklist_completion_test.runs', []);
    $runs[] = $this->getName();
    $state->set('checklist_completion_test.runs', $runs);
    if (!empty($this->configuration['fail'])) {
      throw new \RuntimeException('Test action failed.');
    }
    if (isset($this->configuration['applicability_updates'])) {
      $state->set('checklist_completion_test.applicability', $this->configuration['applicability_updates']);
    }
    if (empty($this->configuration['stay_incomplete'])) {
      $this->getItem()->setComplete(ChecklistItemInterface::METHOD_AUTO);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary(): array {
    return [];
  }

}
