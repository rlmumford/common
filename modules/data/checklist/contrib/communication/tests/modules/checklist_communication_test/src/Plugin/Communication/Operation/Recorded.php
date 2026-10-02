<?php

namespace Drupal\checklist_communication_test\Plugin\Communication\Operation;

use Drupal\communication\Entity\CommunicationInterface;
use Drupal\communication\OperationResult;
use Drupal\communication\Plugin\Communication\Operation\DeferredSaveOperationInterface;
use Drupal\communication\Plugin\Communication\OperationVariant\OperationVariantBase;

/**
 * Records invocation without contacting a delivery provider.
 *
 * @CommunicationOperation(
 *   id = "recorded",
 *   label = @Translation("Record test delivery"),
 *   modes = {},
 *   exclude_modes = {}
 * )
 */
class Recorded extends OperationVariantBase implements DeferredSaveOperationInterface {

  /**
   * Recorded transport calls.
   */
  public static array $calls = [];
  /**
   * Whether the transport reports failure.
   */
  public static bool $fail = FALSE;
  /**
   * Whether the transport throws after receiving the request.
   */
  public static bool $interrupt = FALSE;

  /**
   * {@inheritdoc}
   */
  public function validate(CommunicationInterface $communication, array $options = [], array &$reasons = []) {
    return !in_array($communication->get('status')->value, ['sent', 'failed'], TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function run(CommunicationInterface $communication, array $options = []) {
    return $this->executeWithoutSaving($communication, $options)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function executeWithoutSaving(CommunicationInterface $communication, array $options = []): OperationResult {
    static::$calls[] = [$communication->id(), $communication->label(), $options];
    if (static::$interrupt) {
      throw new \RuntimeException('Uncertain provider response.');
    }
    $communication = clone $communication;
    if (!static::$fail) {
      $communication->set('status', 'sent');
    }
    return new OperationResult($communication, !static::$fail);
  }

}
