<?php

namespace Drupal\identity_service\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\identity\Entity\Identity;
use Drupal\identity\Entity\IdentityData;
use Drupal\identity\Entity\IdentityDataSource;
use Drupal\identity\IdentityDataGroup;
use Drupal\identity\IdentityDataIdentityAcquirer;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
      $container->get('identity.acquirer')
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
    IdentityDataIdentityAcquirer $identity_acquirer
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->serializer = $serializer;
    $this->renderer = $renderer;
    $this->identityAcquirer = $identity_acquirer;
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
      try {
        $this->serializer->denormalize(
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
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Drupal\rest\ResourceResponse
   */
  public function queryData(Request $request) {
    $group = $this->deserializeDataGroup($request);
    $result = $this->identityAcquirer->acquireIdentity($group);

    return new ResourceResponse($result, 200);
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
      $data->setIdentity($identity)->skipIdentitySave()->save();
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
    if ($request->query->type) {
      $data = $identity->getData($request->query->type);
      return new ResourceResponse($data, 200);
    }
    else {
      $data = $identity->getAllData();
      return new ResourceResponse($data, 200);
    }
  }
}
