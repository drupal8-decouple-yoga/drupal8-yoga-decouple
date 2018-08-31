<?php

namespace Drupal\jsonapi_extras;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\Resource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi\Routing\Routes;
use Drupal\jsonapi\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Simplifies the process of generating a JSON API version of an entity.
 *
 * @api
 */
class EntityToJsonApi {

  /**
   * The currently logged in user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Serializer object.
   *
   * @var \Drupal\jsonapi\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The JSON API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The master request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $masterRequest;

  /**
   * The JSON API base path.
   *
   * @var string
   */
  protected $jsonApiBasePath;

  /**
   * EntityToJsonApi constructor.
   *
   * @param \Drupal\jsonapi\Serializer\Serializer $serializer
   *   The serializer.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The resource type repository.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The currently logged in user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param string $jsonapi_base_path
   *   The JSON API base path.
   */
  public function __construct(Serializer $serializer, ResourceTypeRepositoryInterface $resource_type_repository, AccountInterface $current_user, RequestStack $request_stack, $jsonapi_base_path) {
    $this->serializer = $serializer;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->currentUser = $current_user;
    $this->masterRequest = $request_stack->getMasterRequest();
    assert(is_string($jsonapi_base_path));
    assert($jsonapi_base_path[0] === '/');
    assert(isset($jsonapi_base_path[1]));
    assert(substr($jsonapi_base_path, -1) !== '/');
    $this->jsonApiBasePath = $jsonapi_base_path;
  }

  /**
   * Return the requested entity as a raw string.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to generate the JSON from.
   * @param string[] $includes
   *   The list of includes.
   *
   * @return string
   *   The raw JSON string of the requested resource.
   */
  public function serialize(EntityInterface $entity, array $includes = []) {
    return $this->serializer->serialize(new JsonApiDocumentTopLevel($entity),
      'api_json',
      $this->calculateContext($entity, $includes)
    );
  }

  /**
   * Return the requested entity as an structured array.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to generate the JSON from.
   * @param string[] $includes
   *   The list of includes.
   *
   * @return array
   *   The JSON structure of the requested resource.
   */
  public function normalize(EntityInterface $entity, array $includes = []) {
    return $this->serializer->normalize(new JsonApiDocumentTopLevel($entity),
      'api_json',
      $this->calculateContext($entity, $includes)
    )->rasterizeValue();
  }

  /**
   * Calculate the arguments for the serialize/normalize operation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to generate the JSON from.
   * @param string[] $includes
   *   The list of includes.
   *
   * @return array
   *   The list of arguments for serialize/normalize operation.
   */
  protected function calculateContext(
    EntityInterface $entity,
    array $includes = []
  ) {
    $entity_type_id = $entity->getEntityTypeId();
    $resource_type = $this->resourceTypeRepository->get(
      $entity_type_id,
      $entity->bundle()
    );
    // The overridden resource type implementation of "jsonapi_extras" may
    // return a value containing a leading slash. Since this was initial
    // behavior we won't going to break the things and ready to tackle both
    // cases: with or without a leading slash.
    $resource_path = ltrim($resource_type->getPath(), '/');
    $path = sprintf(
      '%s/%s/%s',
      rtrim($this->jsonApiBasePath, '/'),
      rtrim($resource_path, '/'),
      $entity->uuid()
    );
    $request = Request::create($this->masterRequest->getUriForPath($path));

    // We don't have to filter the "$include" since this will be done later.
    // @see JsonApiDocumentTopLevelNormalizer::expandContext()
    $request->query->set('include', implode(',', $includes));
    $request->attributes->set($entity_type_id, $entity);
    $request->attributes->set(Routes::RESOURCE_TYPE_KEY, $resource_type);
    $request->attributes->set(Routes::JSON_API_ROUTE_FLAG_KEY, TRUE);

    return [
      'account' => $this->currentUser,
      'resource_type' => $resource_type,
      'request' => $request,
    ];
  }

}
