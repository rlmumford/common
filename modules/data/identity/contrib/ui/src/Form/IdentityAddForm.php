<?php

namespace Drupal\identity_ui\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\identity\Entity\IdentityDataSource;
use Drupal\identity\IdentityAcquisitionResult;
use Drupal\identity\IdentityDataClassManager;
use Drupal\identity\IdentityDataGroup;
use Drupal\identity\IdentityDataIdentityAcquirer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class IdentityAddForm
 *
 * @package Drupal\identity_ui\Form
 */
class IdentityAddForm extends ContentEntityForm {

  /**
   * @var \Drupal\identity\IdentityDataClassManager
   */
  protected $dataClassManager;

  /**
   * @var \Drupal\identity\IdentityDataIdentityAcquirer
   */
  protected $identityAcquirer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.identity_data_class'),
      $container->get('identity.acquirer'),
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time')
    );
  }

  /**
   * IdentityAddForm constructor.
   *
   * @param \Drupal\identity\IdentityDataClassManager $data_class_manager
   * @param \Drupal\identity\IdentityDataIdentityAcquirer $identity_acquirer
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface|NULL $entity_type_bundle_info
   * @param \Drupal\Component\Datetime\TimeInterface|NULL $time
   */
  public function __construct(
    IdentityDataClassManager $data_class_manager,
    IdentityDataIdentityAcquirer $identity_acquirer,
    EntityTypeManagerInterface $entity_type_manager,
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL,
    TimeInterface $time = NULL
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);

    $this->dataClassManager = $data_class_manager;
    $this->identityAcquirer = $identity_acquirer;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    if (!$form_state->get('mode') || ($form_state->get('mode') === 'add')) {
      return parent::form($form, $form_state);
    }

    /** @var \Drupal\identity\IdentityAcquisitionResult $acquisition_result */
    $acquisition_result = $form_state->get('acquisition_result');

    $this->messenger()->addWarning(new TranslatableMarkup(
      "It looks like this contact might already be in our database."
    ));

    $form['table'] = [
      '#type' => 'table',
      '#header' => [
        new TranslatableMarkup('New Identity'),
      ],
      'select' => [
        'your_submission' => [
          '#type' => 'radio',
          '#return_value' => 'new',
          '#parents' => ['selected'],
        ],
      ],
    ];
    foreach ($acquisition_result->getAllMatches() as $match) {
      $form['table']['#header'][] = $match->getIdentity()->label();
      $form['table']['select']["id_".$match->getIdentity()->id()] = [
        '#type' => 'radio',
        '#return_value' => $match->getIdentity()->id(),
        '#parents' => ['selected'],
      ];
    }

    $data_view_builder = $this->entityTypeManager->getViewBuilder('identity_data');
    foreach ($this->dataClassManager->getDefinitions() as $plugin_id => $definition) {
      $submission_datas = $this->entity->get("{$plugin_id}_data")->referencedEntities();
      $form['table'][$plugin_id] = [
        'your_submission' => $data_view_builder->viewMultiple($submission_datas),
      ];

      foreach ($acquisition_result->getAllMatches() as $match) {
        $form['table'][$plugin_id]["id_".$match->getIdentity()->id()] = $data_view_builder->viewMultiple(
          $match->getIdentity()->getData($plugin_id)
        );
      }
    }

    foreach (Element::children($form['table']) as $child) {
      if ($child === 'select') {
        continue;
      }

      $row = array_filter($form['table'][$child], function($cell) {
        return count(Element::children($cell)) > 0;
      });

      if (!count($row)) {
        unset($form['table'][$child]);
      }
    }

    dpm($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    if ($form_state->get('mode') === 'confirm_acquisition') {
      $actions['submit'] = [
        '#type' => 'submit',
        '#value' => new TranslatableMarkup('Confirm'),
        '#submit' => [
          '::submitFormConfirmAcquisitions', '::save',
        ],
      ];

      return $actions;
    }

    return parent::actions($form, $form_state); // TODO: Change the autogenerated stub
  }

  /**
   * Submit confirm acquisition.
   *
   * @param array $array
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function submitFormConfirmAcquisitions(array &$form, FormStateInterface $form_state) {
    if (is_numeric($form_state->getValue('selected'))) {
      $identity = $this->entityTypeManager->getStorage('identity')->load($form_state->getValue('selected'));
    }
    else {
      $identity = $this->entity;
    }

    $datas = $form_state->get('identity_data');
    foreach ($datas as $data) {
      $data->identity = $identity;
    }

    $this->entity = $identity;

    $form_state->set('mode', 'add');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state); // TODO: Change the autogenerated stub

    // Attempt Acquisition
    $datas = [];
    foreach ($this->dataClassManager->getDefinitions() as $plugin_id => $definition) {
      foreach ($this->entity->get("{$plugin_id}_data") as $item) {
        if ($item->entity) {
          $datas[] = $item->entity;
        }
      }
    }
    $group = new IdentityDataGroup(
      $datas,
      IdentityDataSource::create([
        'label' => 'Form Submission',
        'reference' => "form-submission-".$form['#build_id'],
      ])
    );

    /** @var \Drupal\identity\IdentityAcquisitionResult $result */
    $result = $this->identityAcquirer->acquireIdentity($group, [
      'confidence_threshold' => 10,
    ]);

    if ($result->getMethod() === IdentityAcquisitionResult::METHOD_FOUND) {
      $form_state->set('mode', 'confirm_acquisition');
      $form_state->set('acquisition_result', $result);
      $form_state->setRebuild(TRUE);
    }
    else {
      $form_state->set('mode', 'add');

      foreach ($datas as $data) {
        $data->identity = $this->entity;
      }
    }

    $form_state->set('identity_data', $datas);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    if ($form_state->get('mode') === 'add') {
      if ($this->entity->isNew()) {
        $this->entity->save();
      }

      foreach ($form_state->get('identity_data') as $data) {
        $data->_skipIdentitySave = TRUE;
        $data->save();
      }

      $this->entity->save();
    }
  }
}
