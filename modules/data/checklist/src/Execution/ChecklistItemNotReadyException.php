<?php

namespace Drupal\checklist\Execution;

/**
 * The item cannot currently run because of its state, contexts or gates.
 */
class ChecklistItemNotReadyException extends \DomainException {}
