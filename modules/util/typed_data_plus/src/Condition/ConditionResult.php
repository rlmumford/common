<?php

declare(strict_types=1);

namespace Drupal\typed_data_plus\Condition;

use Drupal\Core\Render\BubbleableMetadata;

/**
 * A valid condition's result, explanations and collected cache metadata.
 */
final class ConditionResult extends BubbleableMetadata {

  public function __construct(protected bool $met, protected array $reasons) {}

  /**
   * Whether the configured condition was satisfied.
   */
  public function isMet(): bool {
    return $this->met;
  }

  /**
   * Returns unmet expression text, without including resolved context values.
   */
  public function getReasons(): array {
    return $this->reasons;
  }

}
