<?php

namespace Drupal\checklist\Plugin\QueueWorker;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\checklist\Attempt\ChecklistAttemptJournal;
use Drupal\checklist\Execution\ChecklistIterationRunner;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Runs one automatic checklist iteration per message.
 *
 * @QueueWorker(
 *   id = "checklist_iteration",
 *   title = @Translation("Run checklist iterations"),
 *   cron = {"time" = 15}
 * )
 */
class ChecklistIteration extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the iteration worker.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected ChecklistAttemptJournal $journal, protected ChecklistIterationRunner $runner, protected LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('checklist.attempt_journal'), $container->get('checklist.iteration_runner'), $container->get('logger.channel.checklist'));
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (
      !is_array($data) || !is_string($data['attempt'] ?? NULL) ||
      !is_int($data['version'] ?? NULL) || $data['version'] < 1
    ) {
      $this->logger->warning('Discarded an invalid checklist iteration message.');
      return;
    }
    $attempt = $this->journal->load($data['attempt']);
    if (
      !$attempt || $attempt->version !== $data['version'] ||
      !in_array($attempt->status, [ChecklistAttempt::QUEUED, ChecklistAttempt::WAITING], TRUE) ||
      $attempt->path !== ChecklistAttempt::ACTION || $attempt->mode !== ChecklistAttempt::INITIAL
    ) {
      return;
    }
    try {
      $this->runner->run($attempt);
    }
    catch (ChecklistAttemptConflictException) {
      // Duplicate deliveries and stale results cannot apply through the runner.
    }
    catch (\Throwable) {
      // A pre-claim rejection remains due and is reconsidered after dispatch
      // expiry. Failed/running attempts are not replayed by the scheduler.
      // Do not hand provider errors to cron's raw exception logger or trigger
      // immediate transport retries. Attempt state governs further execution.
      $this->logger->warning('Checklist iteration could not complete for attempt {attempt}; inspect its current status before retrying.', ['attempt' => $attempt->id]);
    }
  }

}
