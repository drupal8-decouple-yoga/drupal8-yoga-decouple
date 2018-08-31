<?php

namespace Drupal\openapi\Plugin\openapi\OpenApiGenerator;

use Drupal\openapi\Plugin\openapi\OpenApiGeneratorBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\openapi\RestInspectionTrait;
use Drupal\rest\RestResourceConfigInterface;
use Drupal\schemata\SchemaFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an OpenApi Schema Generator for the Rest module.
 *
 * @OpenApiGenerator(
 *   id = "rest",
 *   label = @Translation("Rest"),
 * )
 */
class RestGenerator extends OpenApiGeneratorBase {

  use RestInspectionTrait;

  /**
   * Return resources for non-entity resources.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A json response.
   */
  public function nonBundleResourcesJson() {
    /** @var \Drupal\rest\Entity\RestResourceConfig[] $resource_configs */
    $resource_configs = $this->entityTypeManager
      ->getStorage('rest_resource_config')
      ->loadMultiple();
    $non_entity_configs = [];
    foreach ($resource_configs as $resource_config) {
      if (!$this->isEntityResource($resource_config)) {
        $non_entity_configs[] = $resource_config;
      }
      else {
        $entity_type = $this->getEntityType($resource_config);
        if (!$entity_type->getBundleEntityType()) {
          $non_entity_configs[] = $resource_config;
        }
      }
    }
    $spec = $this->getSpecification($non_entity_configs);
    $response = new JsonResponse($spec);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $bundle_name = isset($this->getOptions()['bundle_name']) ? $this->getOptions()['bundle_name'] : NULL;
    $entity_type_id = isset($this->getOptions()['entity_type_id']) ? $this->getOptions()['entity_type_id'] : NULL;
    static $definitions = [];
    if (!$definitions) {
      $entity_types = $this->getRestEnabledEntityTypes($entity_type_id);
      $definitions = [];
      foreach ($entity_types as $entity_id => $entity_type) {
        $entity_schema = $this->getJsonSchema('json', $entity_id);
        $definitions[$entity_id] = $entity_schema;
        if ($bundle_type = $entity_type->getBundleEntityType()) {
          $bundle_storage = $this->entityTypeManager->getStorage($bundle_type);
          if ($bundle_name) {
            $bundles[$bundle_name] = $bundle_storage->load($bundle_name);
          }
          else {
            $bundles = $bundle_storage->loadMultiple();
          }
          foreach ($bundles as $bundle => $bundle_data) {
            $bundle_schema = $this->getJsonSchema('json', $entity_id, $bundle);
            foreach ($entity_schema['properties'] as $property_id => $property) {
              if (isset($bundle_schema['properties'][$property_id]) && $bundle_schema['properties'][$property_id] === $property) {
                // Remove any bundle schema property that is the same as the
                // entity schema property.
                unset($bundle_schema['properties'][$property_id]);
              }
            }
            // Use Open API polymorphism support to show that bundles extend
            // entity type.
            // @todo Should base fields be removed from bundle schema?
            // @todo Can base fields could be different from entity type base fields?
            // @see hook_entity_bundle_field_info().
            // @see https://github.com/OAI/OpenAPI-Specification/blob/master/versions/2.0.md#models-with-polymorphism-support
            $definitions[$this->getEntityDefinitionKey($entity_type->id(), $bundle)] = [
              'allOf' => [
                ['$ref' => "#/definitions/$entity_id"],
                $bundle_schema,
              ],
            ];

          }
        }
      }
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getConsumes() {
    return $this->generateMimeTypesFromFormats($this->getRestSupportedFormats());
  }

  /**
   * {@inheritdoc}
   */
  public function getProduces() {
    return $this->generateMimeTypesFromFormats($this->getRestSupportedFormats());
  }

  /**
   * Get tags.
   */
  public function getTags() {
    $entity_types = $this->getRestEnabledEntityTypes();
    $tags = [];
    foreach ($entity_types as $entity_type) {
      if ($this->includeEntityTypeBundle($entity_type->id())) {
        $tag = [
          'name' => $entity_type->id(),
          'description' => $this->t("Entity type: @label", ['@label' => $entity_type->getLabel()]),
          'x-entity-type' => $entity_type->id(),
          'x-definition' => [
            '$ref' => '#/definitions/' . $this->getEntityDefinitionKey($entity_type->id()),
          ],
        ];
        $tags[] = $tag;
      }
    }
    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getPaths() {
    $bundle_name = isset($this->getOptions()['bundle_name']) ? $this->getOptions()['bundle_name'] : NULL;
    $resource_configs = $this->getResourceConfigs($this->getOptions());
    if (!$resource_configs) {
      return [];
    }
    $api_paths = [];
    foreach ($resource_configs as $resource_config) {
      /** @var \Drupal\rest\Plugin\ResourceBase $plugin */
      $resource_plugin = $resource_config->getResourcePlugin();
      foreach ($resource_config->getMethods() as $method) {
        if ($route = $this->getRouteForResourceMethod($resource_config, $method)) {
          $open_api_method = strtolower($method);
          $path = $route->getPath();
          $path_method_spec = [];
          $formats = $this->getMethodSupportedFormats($method, $resource_config);
          $format_parameter = [
            'name' => '_format',
            'in' => 'query',
            'type' => 'string',
            'enum' => $formats,
            'required' => TRUE,
            'description' => 'Request format',
          ];
          if (count($formats) == 1) {
            $format_parameter['default'] = $formats[0];
          }
          $path_method_spec['parameters'][] = $format_parameter;

          $path_method_spec['responses'] = $this->getErrorResponses();

          if ($this->isEntityResource($resource_config)) {
            $entity_type = $this->getEntityType($resource_config);
            $path_method_spec['tags'] = [$entity_type->id()];
            $path_method_spec['summary'] = $this->t('@method a @entity_type', [
              '@method' => ucfirst($open_api_method),
              '@entity_type' => $entity_type->getLabel(),
            ]);
            $path_method_spec['parameters'] = array_merge($path_method_spec['parameters'], $this->getEntityParameters($entity_type, $method, $bundle_name));
            $path_method_spec['responses'] = $this->getEntityResponses($entity_type->id(), $method, $bundle_name) + $path_method_spec['responses'];
          }
          else {
            $path_method_spec['responses']['200'] = [
              'description' => 'successful operation',
            ];
            $path_method_spec['summary'] = $resource_plugin->getPluginDefinition()['label'];
            $path_method_spec['parameters'] = array_merge($path_method_spec['parameters'], $this->getRouteParameters($route));

          }

          $path_method_spec['operationId'] = $resource_plugin->getPluginId() . ":" . $method;
          $path_method_spec['schemes'] = [$this->request->getScheme()];
          $path_method_spec['security'] = $this->getResourceSecurity($resource_config, $method, $formats);
          $api_paths[$path][$open_api_method] = $path_method_spec;
        }
      }
    }
    return $api_paths;
  }

  /**
   * Gets the matching for route for the resource and method.
   *
   * @param \Drupal\rest\RestResourceConfigInterface $resource_config
   *   The REST config resource.
   * @param string $method
   *   The HTTP method.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   *
   * @throws \Exception
   *   If no route is found.
   */
  protected function getRouteForResourceMethod(RestResourceConfigInterface $resource_config, $method) {
    if ($this->isEntityResource($resource_config)) {
      $route_name = 'rest.' . $resource_config->id() . ".$method";

      $routes = $this->routingProvider->getRoutesByNames([$route_name]);
      if (empty($routes)) {
        $formats = $resource_config->getFormats($method);
        if (count($formats) > 0) {
          $route_name .= ".{$formats[0]}";
          $routes = $this->routingProvider->getRoutesByNames([$route_name]);
        }
      }
      if ($routes) {
        return array_pop($routes);
      }
    }
    else {
      $resource_plugin = $resource_config->getResourcePlugin();
      foreach ($resource_plugin->routes() as $route) {
        $methods = $route->getMethods();
        if (array_search($method, $methods) !== FALSE) {
          return $route;
        }
      };
    }
    throw new \Exception("No route found for REST resource, {$resource_config->id()}, for method $method");
  }

  /**
   * Get the error responses.
   *
   * @see https://github.com/OAI/OpenAPI-Specification/blob/master/versions/2.0.md#responseObject
   *
   * @return array
   *   Keys are http codes. Values responses.
   */
  protected function getErrorResponses() {
    $responses['400'] = [
      'description' => 'Bad request',
      'schema' => [
        'type' => 'object',
        'properties' => [
          'error' => [
            'type' => 'string',
            'example' => 'Bad data',
          ],
        ],
      ],
    ];
    $responses['500'] = [
      'description' => 'Internal server error.',
      'schema' => [
        'type' => 'object',
        'properties' => [
          'message' => [
            'type' => 'string',
            'example' => 'Internal server error.',
          ],
        ],
      ],
    ];
    return $responses;
  }

  /**
   * Get parameters for an entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $method
   *   The HTTP method.
   * @param string $bundle_name
   *   The bundle name.
   *
   * @return array
   *   Parameters for the entity resource.
   */
  protected function getEntityParameters(EntityTypeInterface $entity_type, $method, $bundle_name = NULL) {
    $parameters = [];
    if (in_array($method, ['GET', 'DELETE', 'PATCH'])) {
      $keys = $entity_type->getKeys();
      if ($entity_type instanceof ConfigEntityTypeInterface) {
        $key_type = 'string';
      }
      else {
        if ($entity_type instanceof FieldableEntityInterface) {
          $key_field = $this->fieldManager->getFieldStorageDefinitions($entity_type->id())[$keys['id']];
          $key_type = $key_field->getType();
        }
        else {
          $key_type = 'string';
        }

      }

      $parameters[] = [
        'name' => $entity_type->id(),
        'in' => 'path',
        'required' => TRUE,
        'type' => $key_type,
        'description' => $this->t('The @id,id, of the @type.', [
          '@id' => $keys['id'],
          '@type' => $entity_type->id(),
        ]),
      ];
    }
    if (in_array($method, ['POST', 'PATCH'])) {
      $parameters[] = [
        'name' => 'body',
        'in' => 'body',
        'description' => $this->t('The @label object', ['@label' => $entity_type->getLabel()]),
        'required' => TRUE,
        'schema' => [
          '$ref' => '#/definitions/' . $this->getEntityDefinitionKey($entity_type->id(), $bundle_name),
        ],
      ];
    }
    return $parameters;
  }

  /**
   * Get OpenAPI parameters for a route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route.
   *
   * @return array
   *   The resource parameters.
   */
  protected function getRouteParameters(Route $route) {
    $parameters = [];
    $vars = $route->compile()->getPathVariables();
    foreach ($vars as $var) {
      $parameters[] = [
        'name' => $var,
        'type' => 'string',
        'in' => 'path',
        'required' => TRUE,
      ];
    }
    return $parameters;
  }

  /**
   * Get the security information for the a resource.
   *
   * @param \Drupal\rest\RestResourceConfigInterface $resource_config
   *   The REST resource.
   * @param string $method
   *   The HTTP method.
   * @param string[] $formats
   *   The formats.
   *
   * @return array
   *   The security elements.
   *
   * @see http://swagger.io/specification/#securityDefinitionsObject
   */
  public function getResourceSecurity(RestResourceConfigInterface $resource_config, $method, array $formats) {
    $security = [];
    foreach ($resource_config->getAuthenticationProviders($method) as $auth) {
      switch ($auth) {
        case 'basic_auth':
        case 'cookie':
        case 'oauth':
        case 'oauth2':
          // @TODO: #2977109 - Calculate oauth scopes required.
          $security[] = [$auth => []];
          break;
      }
    }
    // @todo Handle tokens that need to be set in headers.
    if ($this->isEntityResource($resource_config)) {

      $route_name = 'rest.' . $resource_config->id() . ".$method";

      $routes = $this->routingProvider->getRoutesByNames([$route_name]);
      if (empty($routes) && count($formats) > 1) {
        $route_name .= ".{$formats[0]}";
        $routes = $this->routingProvider->getRoutesByNames([$route_name]);
      }
      if ($routes) {
        $route = array_pop($routes);
        // Check to see if route is protected by access checks in header.
        if ($route->getRequirement('_csrf_request_header_token')) {
          $security[] = ['csrf_token' => []];
        }
      }
    }
    return $security;
  }

  /**
   * {@inheritdoc}
   */
  public function getApiName() {
    return $this->t('REST API');
  }

  /**
   * {@inheritdoc}
   */
  protected function getApiDescription() {
    return $this->t('The REST API provide by the core REST module.');
  }

  /**
   * List of supported Serializer Formats for HTTP Method on a Rest Resource.
   *
   * @param string $method
   *   The HTTP method for generating the MIME Types. Example: GET, POST, etc.
   * @param \Drupal\rest\RestResourceConfigInterface $resource_config
   *   The resource configuration.
   *
   * @return array
   *   The list of MIME Types
   */
  protected function getMethodSupportedFormats($method, RestResourceConfigInterface $resource_config) {
    if (empty($method)) {
      return [];
    }
    // The route ID.
    $route_id = "rest.{$resource_config->id()}.$method";

    // First Check the supported formats on the route level.
    /** @var \Symfony\Component\Routing\Route[] $route */
    $routes = $this->routingProvider->getRoutesByNames([$route_id]);
    if (!empty($routes) && array_key_exists($route_id, $routes) && $formats = $routes[$route_id]->getRequirement('_format')) {
      return explode('|', $formats);
    }

    // If no route level format was found, lets use
    // the RestResourceConfig formats for the given HTTP method.
    return $resource_config->getFormats($method);
  }

  /**
   * Generate list of MIME Types based on a list of serializer formats.
   *
   * @param array $formats
   *   List of formats.
   *
   * @return array
   *   List of MIME Types based on $formats.
   *   The list is MIME Types are on the same order as the inserted $format
   */
  protected function generateMimeTypesFromFormats(array $formats) {
    $mime_types = [];
    foreach ($formats as $format) {
      $mime_types[] = 'application/' . preg_replace('/_/', '+', trim(strtolower($format)));
    }
    return $mime_types;
  }

  /**
   * Returns a list of supported Format on REST.
   *
   * @return array
   *   The list of supported formats.
   */
  protected function getRestSupportedFormats() {
    static $supported_formats = [];
    if (empty($supported_formats)) {
      $resource_configs = $this->getResourceConfigs($this->getOptions());
      if (empty($resource_configs)) {
        return [];
      }
      foreach ($resource_configs as $resource_config) {
        /** @var \Drupal\rest\Plugin\ResourceBase $plugin */
        $resource_plugin = $resource_config->getResourcePlugin();
        foreach ($resource_config->getMethods() as $method) {
          $formats = $this->getMethodSupportedFormats($method, $resource_config);
          $supported_formats = array_unique(array_merge($supported_formats, $formats), SORT_REGULAR);
        }
      }
    }
    return $supported_formats;
  }

}
