<?php

namespace Drupal\checklist_flexiform_test\Plugin\FormDataProvider;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\flexiform\FormData\FormData;
use Drupal\flexiform\FormData\FormDataProviderBase;
use Drupal\flexiform\FormData\FormDataStorageInterface;
use Drupal\flexiform\FormData\PreparingDataProviderInterface;

/**
 * Exercises retained preparation and transactional save failures.
 *
 * @FormDataProvider(
 *   id = "checklist_pending_data",
 *   label = @Translation("Pending test data")
 * )
 */
class Pending extends FormDataProviderBase implements PreparingDataProviderInterface, FormDataStorageInterface {

  /**
   * Retained preparation count.
   */
  protected int $passes = 0;

  /**
   * Whether persistence should fail for this test.
   */
  public static bool $fail = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getDataContextDefinition(): ContextDefinitionInterface {
    return ContextDefinition::create('string')->setLabel('Prepared data');
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(int $remainingMilliseconds = 2000): bool {
    return ++$this->passes > 1;
  }

  /**
   * {@inheritdoc}
   */
  public function load(): FormData {
    return new FormData(new Context($this->getDataContextDefinition(), 'Prepared'), $this);
  }

  /**
   * {@inheritdoc}
   */
  public function save(FormData $data, array $settings): void {
    if (static::$fail) {
      throw new \RuntimeException('Test persistence failure.');
    }
  }

}
