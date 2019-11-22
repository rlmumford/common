<?php

namespace Drupal\identity_service\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\ConditionInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\identity\Entity\Identity;
use Drupal\identity\Entity\IdentityData;
use Drupal\identity\Entity\IdentityDataSource;
use Drupal\identity\IdentityDataGroup;
use Drupal\identity\IdentityDataIdentityAcquirer;
use Drupal\identity\IdentityLabelContext;
use Drupal\identity\IdentityLabelerInterface;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;

class ServiceController extends ControllerBase {

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface|\Symfony\Component\Serializer\Encoder\DecoderInterface
   */
  protected $serializer;

  /**
   * The identity acquirer.
   *
   * @var \Drupal\identity\IdentityDataIdentityAcquirer
   */
  protected $identityAcquirer;

  /**
   * The identity labeler service
   *
   * @var \Drupal\identity\IdentityLabelerInterface
   */
  protected $identityLabeler;

  /**
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('serializer'),
      $container->get('renderer'),
      $container->get('identity.acquirer'),
      $container->get('identity.labeler')
    );
  }

  /**
   * ServiceController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   * @param \Drupal\identity\IdentityDataIdentityAcquirer $identity_acquirer
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    SerializerInterface $serializer,
    RendererInterface $renderer,
    IdentityDataIdentityAcquirer $identity_acquirer,
    IdentityLabelerInterface $identity_labeler
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->serializer = $serializer;
    $this->renderer = $renderer;
    $this->identityAcquirer = $identity_acquirer;
    $this->identityLabeler = $identity_labeler;
  }

  /**
   * Deserialize a supplied data group.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Drupal\identity\IdentityDataGroup|NULL
   */
  protected function deserializeDataGroup(Request $request) {
    $received = $request->getContent();
    $unserialized = NULL;
    if (!empty($received)) {
      $method = strtolower($request->getMethod());
      $format = $request->getContentType();

      try {
        $unserialized = $this->serializer->decode($received, $format, ['request_method' => $method]);
      }
      catch (UnexpectedValueException $e) {
        throw new BadRequestHttpException($e->getMessage(), $e);
      }
    }

    if (empty($unserialized['data'])) {
      throw new BadRequestHttpException(new TranslatableMarkup('No identity data supplied.'));
    }

    $data = [];
    foreach ($unserialized['data'] as $unserialized_data) {
      // We need to massage data a little.
      foreach ($unserialized_data as $key => $field_value) {
        if ($key == 'class') {
          continue;
        }

        // If the value is scalar or the value is an associative array then we
        // need to make it an array to allow the normalizer to interpret deltas.
        if (!is_array($field_value) || is_string(key($field_value))) {
          $unserialized_data[$key] = [
            $unserialized_data[$key]
          ];
        }
      }

      try {
        $datum = $this->serializer->denormalize(
          $unserialized_data,
          IdentityData::class,
          $format,
          ['request_method' => $method]
        );
        $data[] = $datum;
      }
      catch (UnexpectedValueException $e) {
        throw new BadRequestHttpException($e->getMessage(), $e);
      }
      catch (InvalidArgumentException $e) {
        throw new BadRequestHttpException($e->getMessage(), $e);
      }
    }

    $source = NULL;
    if (!empty($unserialized['source'])) {
      foreach ($unserialized['source'] as $key => $value) {
        // If the value is scalar or the value is an associative array then we
        // need to make it an array to allow the normalizer to interpret deltas.
        if (!is_array($value) || is_string(key($value))) {
          $unserialized['source'][$key] = [
            $unserialized['source'][$key]
          ];
        }
      }

      try {
        $source = $this->serializer->denormalize(
          $unserialized['source'],
          IdentityDataSource::class,
          $format,
          ['request_method' => $method]
        );
      }
      catch (UnexpectedValueException $e) {
        throw new BadRequestHttpException($e->getMessage(), $e);
      }
      catch (InvalidArgumentException $e) {
        throw new BadRequestHttpException($e->getMessage(), $e);
      }
    }

    $id = !empty($unserialized['id']) ? $unserialized['id'] : NULL;
    return new IdentityDataGroup($data, $source, $id);
  }

  /**
   * Deserialize identity data request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Drupal\identity\Entity\IdentityDataInterface
   */
  protected function deserializeData(Request $request) {
    $received = $request->getContent();
    $unserialized = NULL;
    if (!empty($received)) {
      $method = strtolower($request->getMethod());
      $format = $request->getContentType();

      try {
        $unserialized = $this->serializer->decode($received, $format, ['request_method' => $method]);
      }
      catch (UnexpectedValueException $e) {
        throw new BadRequestHttpException($e->getMessage(), $e);
      }
    }

    try {
      $data = $this->serializer->denormalize(
        $unserialized,
        IdentityData::class,
        $format,
        ['request_method' => $method]
      );

      return $data;
    }
    catch (UnexpectedValueException $e) {
      throw new BadRequestHttpException($e->getMessage(), $e);
    }
    catch (InvalidArgumentException $e) {
      throw new BadRequestHttpException($e->getMessage(), $e);
    }
  }

  /**
   * Query Data Callback
   *
   * This is the endpoint for querying identity data. Accepted request query
   * parameters:
   * - conditions: an array of conditions for the query.
   * - label_dpclass: (string) the preferred class to use to generate the label
   * - label_dptype: (string) the preferred type to use to generate the label
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Drupal\rest\ResourceResponse
   */
  public function queryData(Request $request) {
    $storage = $this->entityTypeManager->getStorage('identity');

    $conditions = $request->query->get('conditions', []);

    $query = $storage->getQuery(
      !empty($conditions['conjunction']) ? $conditions['conjunction'] : 'AND'
    );
    $this->queryDataCompileConditions($query, $conditions);

    // Prepare labeling context.
    $label_context = new IdentityLabelContext(array_filter([
      IdentityLabelContext::DATA_PREFERENCE_CLASS => $request->query->get('label_dpclass', NULL),
      IdentityLabelContext::DATA_PREFERENCE_TYPE => $request->query->get('label_dptype', NULL),
    ]));

    $ids = $query->execute();
    $result = [];
    foreach ($storage->loadMultiple($ids) as $identity) {
      $result[] = [
        'id' => $identity->id(),
        'uuid' => $identity->uuid(),
        'label' => $this->identityLabeler->label($identity, $label_context),
        'relevance' => 1,
      ];
    }

    return new ResourceResponse($result, 200);
  }

  /**
   * Compile a condition set into a query object.
   *
   * @param ConditionInterface|\Drupal\Core\Entity\Query\QueryInterface $condition_set
   * @param array $conditions
   */
  protected function queryDataCompileConditions($condition_set, array $conditions) {
    foreach ($conditions as $key => $condition) {
      if (!is_numeric($key)) {
        continue;
      }

      if (isset($condition['_t']) && ($condition['_t'] === 'set')) {
        if ($condition['conjunction'] === 'AND') {
          $condition_group = $condition_set->andConditionGroup();
        }
        else {
          $condition_group = $condition_set->orConditionGroup();
        }

        $this->queryDataCompileConditions($condition_group, $condition);
        $condition_set->condition($condition_group);
      }
      else {
        $class = $condition['class'];

        foreach ($condition as $field => $value) {
          if (in_array($field, ['_t', 'class'])) {
            continue;
          }

          $condition_set->condition($class.'::'.$field, $value['value'], $value['op']);
        }
      }
    }
  }

  /**
   * Post Data Callback
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Drupal\rest\ResourceResponse
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function postData(Request $request) {
    $group = $this->deserializeDataGroup($request);
    $result = $this->identityAcquirer->acquireIdentity($group);

    $identity = $result->getIdentity();
    foreach ($group->getDatas() as $data) {
      $data->setSource($group->getSource())
        ->setIdentity($identity)
        ->skipIdentitySave()
        ->save();
    }
    $identity->save();

    return new ResourceResponse($result, 200);
  }

  /**
   * Post a chunk of data to a known identity.
   *
   * @param \Drupal\identity\Entity\Identity $identity
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function postIdentityData(Identity $identity, Request $request) {
    $data = $this->deserializeData($request);
    $data->setIdentity($identity);
    $data->save();

    return new Response($data->id(), 200);
  }

  /**
   * Get identity data.
   *
   * @param \Drupal\identity\Entity\Identity $identity
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Drupal\rest\ResourceResponse
   */
  public function getIdentityData(Identity $identity, Request $request) {
    if ($request->query->get('class')) {
      $data = $identity->getData($request->query->get('class'));
      return new ResourceResponse($data, 200);
    }
    else {
      $data = $identity->getAllData();
      return new ResourceResponse($data, 200);
    }
  }

  /**
   * Get the identity label
   *
   * @param \Drupal\identity\Entity\Identity $identity
   * @param \Symfony\Component\HttpFoundation\Request $request
   */
  public function getIdentityLabel(Identity $identity, Request $request) {
    $context = new IdentityLabelContext(array_filter([
      IdentityLabelContext::DATA_PREFERENCE_CLASS => $request->query->get('class_preference'),
      IdentityLabelContext::DATA_PREFERENCE_TYPE => $request->query->get('type_preference'),
    ]));
    $label = $this->identityLabeler->label($identity, $context);

    return new JsonResponse(['label' => $label], 200);
  }
}
