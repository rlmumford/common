<?php
/**
 * Created by PhpStorm.
 * User: Rob
 * Date: 28/11/2019
 * Time: 11:20
 */

namespace Drupal\identity_ui\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\identity\Entity\Identity;
use Drupal\identity\IdentityMergerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IdentityMergeForm extends FormBase {

  /**
   * @var \Drupal\identity\IdentityMergerInterface
   */
  protected $merger;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('identity.merger'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * IdentityMergeForm constructor.
   *
   * @param \Drupal\identity\IdentityMergerInterface $merger
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(
    IdentityMergerInterface $merger,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->merger = $merger;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'identity_merge_form';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    Identity $identity1 = NULL,
    Identity $identity2 = NULL
  ) {
    if (!$form_state->get('identity1') && $identity1) {
      $form_state->set('identity1', $identity1);
    }

    if (!$form_state->get('identity2') && $identity2) {
      $form_state->set('identity2', $identity2);
    }

    $view_builder = $this->entityTypeManager->getViewBuilder('identity');
    foreach (['identity1', 'identity2'] as $key) {
      $title = $key == 'identity1' ? new TranslatableMarkup('First Identity') : new TranslatableMarkup('Second Identity');
      if (!$form_state->get($key)) {
        $form[$key] = [
          '#type' => 'entity_autocomplete',
          '#title' => $title,
          '#target_type' => 'identity',
        ];
      }
      else {
        $form[$key] = [
          '#type' => 'container',
          '#title' => $title,
        ] + $view_builder->view($form_state->get($key), 'full');
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    if ($form_state->get('identity1') && $form_state->get('identity2')) {
      $form['actions']['confirm'] = [
        '#value' => new TranslatableMarkup('Confirm Merge'),
        '#type' => 'submit',
        '#submit' => [
          '::submitForm',
        ],
      ];
    }
    else {
      $form['actions']['select'] = [
        '#value' => new TranslatableMarkup('Update Identities'),
        '#type' => 'submit',
        '#submit' => [
          '::submitFormUpdateIdentities',
        ],
      ];
    }

    return $form;
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function submitFormUpdateIdentities(array &$form, FormStateInterface $form_state) {
    foreach (['identity1', 'identity2'] as $key) {
      if ($form_state->getValue($key)) {
        $form_state->set(
          $key,
          $this->entityTypeManager
            ->getStorage('identity')
            ->load($form_state->getValue($key))
        );
      }
    }

    $form_state->setRebuild(TRUE);
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->merger->mergeIdentities(
      $form_state->get('identity1'),
      $form_state->get('identity2')
    );

    $this->messenger()->addStatus(
      'Successfully merged identities.'
    );
  }
}
