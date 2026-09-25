<?php

namespace Drupal\checklist;

/**
 * A renderable resource supplied by a checklist item handler.
 */
final class ChecklistActionResource {

  /**
   * Constructs a resource descriptor.
   *
   * @param string $key
   *   A stable key shared by items that use the same resource.
   * @param array $content
   *   A Drupal render array. Keeping it renderable preserves access and cache
   *   metadata from the resource's contents.
   * @param string|null $label
   *   The label shown for the resource, or NULL to use the item label.
   * @param int $weight
   *   The resource ordering weight.
   * @param bool $closeable
   *   Whether the resource may be dismissed by a consumer.
   * @param string|null $icon
   *   An optional icon identifier for consumers that provide icons.
   * @param bool $pinned
   *   Whether the resource should remain prominent in the pane.
   */
  public function __construct(
    protected string $key,
    protected array $content,
    protected ?string $label = NULL,
    protected int $weight = 0,
    protected bool $closeable = TRUE,
    protected ?string $icon = NULL,
    protected bool $pinned = FALSE,
  ) {
    if ($key === '') {
      throw new \InvalidArgumentException('A checklist action resource requires a non-empty key.');
    }
  }

  /**
   * Gets the shared resource key.
   */
  public function getKey(): string {
    return $this->key;
  }

  /**
   * Gets the resource render array.
   */
  public function getContent(): array {
    return $this->content;
  }

  /**
   * Gets the optional resource label.
   */
  public function getLabel(): ?string {
    return $this->label;
  }

  /**
   * Gets the resource ordering weight.
   */
  public function getWeight(): int {
    return $this->weight;
  }

  /**
   * Determines whether the resource may be dismissed.
   */
  public function isCloseable(): bool {
    return $this->closeable;
  }

  /**
   * Gets the optional icon identifier.
   */
  public function getIcon(): ?string {
    return $this->icon;
  }

  /**
   * Determines whether the resource is pinned.
   */
  public function isPinned(): bool {
    return $this->pinned;
  }

}
