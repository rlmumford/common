<?php

namespace Drupal\checklist\Execution;

/**
 * Server-established execution identity and its auditable authority.
 */
final class ChecklistExecutionAuthorization {

  /**
   * Constructs a grant established by trusted integration code.
   *
   * @param int $executor
   *   The resolved execution user ID.
   * @param array $provenance
   *   Public audit metadata, including string source and integer authorizer.
   *   Stable grant identifiers belong here; credentials and payloads do not.
   */
  public function __construct(
    public readonly int $executor,
    public readonly array $provenance,
  ) {}

}
