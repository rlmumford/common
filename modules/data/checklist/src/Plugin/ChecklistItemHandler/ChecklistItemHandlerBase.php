<?php

namespace Drupal\checklist\Plugin\ChecklistItemHandler;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginWithFormsTrait;

/**
 * Base class for checklist item handlers.
 */
abstract class ChecklistItemHandlerBase extends PluginBase implements ChecklistItemHandlerInterface {
  use PluginWithFormsTrait;

  /**
   * The name of this checklist item.
   *
   * @var string
   */
  protected $name;

  /**
   * The checklist item object.
   *
   * @var \Drupal\checklist\Entity\ChecklistItemInterface
   *
   * @todo See if we can remove this from this class.
   */
  protected $item;

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function setName(string $name): ChecklistItemHandlerInterface {
    $this->name = $name;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): ?string {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function setItem(ChecklistItemInterface $item): ChecklistItemHandlerInterface {
    $this->item = $item;
    $this->name = $item->getName();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getItem(): ChecklistItemInterface {
    return $this->item;
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(): ?bool {
    // @todo Implement sensible default behaviour based on condition plugins presumably.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isRequired(): bool {
    // @todo Implement sensible default behaviour based on condition plugins presumably.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isActionable(): bool {
    // @todo Implement dependencies
    // @todo Implement condition plugins.
    return TRUE;
  }

}
