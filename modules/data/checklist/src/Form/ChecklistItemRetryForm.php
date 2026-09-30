<?php

namespace Drupal\checklist\Form;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\checklist\Attempt\ChecklistAttemptJournal;
use Drupal\checklist\ChecklistRowUpdater;
use Drupal\checklist\ChecklistTempstoreRepository;
use Drupal\checklist\Execution\ChecklistItemExecutionPreparer;
use Drupal\checklist\Execution\ChecklistItemExecutor;
use Drupal\checklist\Execution\ChecklistItemNotReadyException;
use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Confirms a retry of one specific failed automatic attempt.
 */
class ChecklistItemRetryForm extends FormBase {

  /**
   * Constructs the retry form.
   */
  public function __construct(
    protected ChecklistItemExecutionPreparer $preparer,
    protected ChecklistAttemptJournal $journal,
    protected ChecklistItemExecutor $executor,
    protected ChecklistRowUpdater $rowUpdater,
    protected ChecklistTempstoreRepository $tempstore,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('checklist.item_execution_preparer'),
      $container->get('checklist.attempt_journal'),
      $container->get('checklist.item_executor'),
      $container->get('checklist.row_updater'),
      $container->get('checklist.tempstore_repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'checklist_item_retry';
  }

  /**
   * Builds a read-only confirmation pinned to the addressed attempt.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $item_uuid = NULL, ?string $attempt_id = NULL) {
    [, $item] = $this->loadItem($item_uuid);
    if ($this->getRequest()->isMethod('POST')) {
      $form_state->setCached();
    }
    $wrapper = Html::getId('checklist-retry-' . $attempt_id);
    $form['#prefix'] = '<div id="' . $wrapper . '">';
    $form['#suffix'] = '</div>';
    $form['#title'] = $this->t('Retry: @title', ['@title' => $item->get('title')->value]);
    $form['#cache']['max-age'] = 0;
    $form['#attributes']['class'][] = 'checklist-item-retry-form';
    $form['#attached']['library'][] = 'checklist/retry';
    $form['messages'] = ['#type' => 'status_messages'];
    if ($form_state->get('retry_queued')) {
      $form['result'] = ['#markup' => $this->t('Retry queued. You can return to the checklist while it runs.')];
      return $form;
    }
    $attempt = $this->journal->load($attempt_id);
    if (!$attempt || $attempt->itemUuid !== $item->uuid()) {
      throw new NotFoundHttpException();
    }
    $latest = $this->journal->latest($item);
    // Keep the triggering button when reconstructing an uncached POST. The
    // executor rejects its stale attempt; the rebuild then removes controls.
    $submitting = !$form_state->isRebuilding() && ($form_state->getUserInput()['form_id'] ?? NULL) === $this->getFormId();
    if (!$submitting && ($latest?->id !== $attempt_id || $attempt->status !== ChecklistAttempt::FAILED || $attempt->path !== ChecklistAttempt::ACTION || $item->isComplete())) {
      $form['changed'] = ['#markup' => $this->t('This failed attempt is no longer available to retry. Refresh the checklist before trying again.')];
      return $form;
    }
    // Server-side form state, never a hidden value supplied by the browser.
    if (!$form_state->has('retry_expected')) {
      $form_state->set('retry_expected', $attempt);
    }
    $form['explanation'] = [
      '#markup' => $this->t('Choose how to retry this item. Existing results and history will be kept.'),
    ];
    $form['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Saved progress'),
      '#options' => [
        ChecklistAttempt::RESUME => $this->t('Resume — keep saved progress'),
        ChecklistAttempt::FRESH => $this->t('Start fresh — discard saved progress'),
      ],
      '#default_value' => ChecklistAttempt::RESUME,
      '#required' => TRUE,
      '#description' => $this->t('Starting fresh does not undo actions already taken.'),
    ];
    $form['execution'] = ['#markup' => $this->t('The retry will run in the background as you. It may not start immediately.')];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Queue retry'),
      '#button_type' => 'primary',
      '#ajax' => ['callback' => '::ajaxSubmit', 'wrapper' => $wrapper],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => $this->t('Cancel'),
      '#attributes' => ['type' => 'button', 'class' => ['checklist-retry-cancel']],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $expected = $form_state->get('retry_expected');
    if (!$expected instanceof ChecklistAttempt) {
      throw new NotFoundHttpException();
    }
    [, $item] = $this->loadItem($expected->itemUuid);
    try {
      $this->executor->retry($item, $expected, $form_state->getValue('mode'), TRUE);
      $form_state->set('retry_queued', TRUE);
    }
    catch (ChecklistAttemptConflictException) {
      $this->messenger()->addError($this->t('This attempt has changed. No retry was queued. Refresh the checklist before trying again.'));
    }
    catch (ChecklistItemNotReadyException) {
      $this->messenger()->addError($this->t('This item is not ready to run. Resolve its requirements before retrying.'));
    }
    catch (\DomainException) {
      $this->messenger()->addError($this->t('This item can no longer be retried. Refresh the checklist before trying again.'));
    }
    $form_state->setRebuild();
  }

  /**
   * Closes the confirmation and refreshes rows without discarding other forms.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    if (!$form_state->get('retry_queued')) {
      return $form;
    }
    [$checklist] = $this->loadItem($form_state->get('retry_expected')->itemUuid);
    $checklist = $this->tempstore->get($checklist);
    $response = new AjaxResponse();
    $this->rowUpdater->refresh($response, $checklist);
    $response->addCommand(new MessageCommand($this->t('Retry queued. Progress will update as it runs.')));
    return $response;
  }

  /**
   * Authorizes a fresh visible binding, treating missing items as not found.
   */
  protected function loadItem(string $item_uuid): array {
    try {
      [$checklist, $item] = $this->preparer->load($item_uuid);
    }
    catch (\DomainException $exception) {
      throw new NotFoundHttpException('Checklist item not found.', $exception);
    }
    if (!$item->access('view action state')) {
      throw new NotFoundHttpException();
    }
    return [$checklist, $item];
  }

}
