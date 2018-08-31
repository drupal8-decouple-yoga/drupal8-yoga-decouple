<?php

namespace Drupal\openapi\Plugin\openapi\OpenApiGenerator;

use Drupal\openapi\Plugin\openapi\OpenApiGeneratorBase;
use Drupal\Core\Authentication\AuthenticationCollectorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\schemata\SchemaFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Drupal\Core\ParamConverter\ParamConverterManagerInterface;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\Routing\Routes as JsonApiRoutes;

/**
 * Defines an OpenApi Schema Generator for the JsonApi module.
 *
 * @OpenApiGenerator(
 *   id = "jsonapi",
 *   label = @Translation("JsonApi"),
 * )
 */
class JsonApiGenerator extends OpenApiGeneratorBase {

  const JSON_API_UUID_CONVERTER = 'paramconverter.jsonapi.entity_uuid';

  /**
   * Separator for using in definition id strings.
   *
   * Override the default one to use '--' and match jsonapi.
   *
   * @var string
   */
  static $DEFINITION_SEPARATOR = '--';

  /**
   * List of parameters hat should be filtered out on JSON API Routes.
   *
   * @var string[]
   */
  static $PARAMETERS_FILTER_LIST = [
    JsonApiRoutes::RESOURCE_TYPE_KEY,
  ];

  /**
   * Module Handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * Parameter Converter Manager.
   *
   * @var \Drupal\Core\ParamConverter\ParamConverterManagerInterface
   */
  private $paramConverterManager;

  /**
   * JsonApiGenerator constructor.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Unique plugin id.
   * @param array|mixed $plugin_definition
   *   Plugin instance definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteProviderInterface $routing_provider
   *   The routing provider.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The field manager.
   * @param \Drupal\schemata\SchemaFactory $schema_factory
   *   The schema factory.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The current request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration object factory.
   * @param \Drupal\Core\Authentication\AuthenticationCollectorInterface $authentication_collector
   *   The authentication collector.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\ParamConverter\ParamConverterManagerInterface $param_converter_manager
   *   The parameter converter manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, RouteProviderInterface $routing_provider, EntityFieldManagerInterface $field_manager, SchemaFactory $schema_factory, SerializerInterface $serializer, RequestStack $request_stack, ConfigFactoryInterface $config_factory, AuthenticationCollectorInterface $authentication_collector, ModuleHandlerInterface $module_handler, ParamConverterManagerInterface $param_converter_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $routing_provider, $field_manager, $schema_factory, $serializer, $request_stack, $config_factory, $authentication_collector);
    $this->moduleHandler = $module_handler;
    $this->paramConverterManager = $param_converter_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('router.route_provider'),
      $container->get('entity_field.manager'),
      $container->get('schemata.schema_factory'),
      $container->get('serializer'),
      $container->get('request_stack'),
      $container->get('config.factory'),
      $container->get('authentication_collector'),
      $container->get('module_handler'),
      $container->get('paramconverter_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getBasePath() {
    return parent::getBasePath() . $this->getJsonApiBase();
  }

  /**
   * Determine the base for JsonApi's endpoint routes.
   *
   * @return string
   *   The url prefix used for all jsonapi resource endpoints.
   */
  public function getJsonApiBase() {
    $root = '/jsonapi';
    if ($this->moduleHandler->moduleExists('jsonapi_extras')) {
      $root = '/' . $this->configFactory
        ->get('jsonapi_extras.settings')
        ->get('path_prefix');
    }
    return $root;
  }

  /**
   * {@inheritdoc}
   */
  public function getPaths() {
    $routes = $this->getJsonApiRoutes();
    $api_paths = [];
    foreach ($routes as $route_name => $route) {
      /** @var \Drupal\jsonapi\ResourceType\ResourceType $resource_type */
      $resource_type = $this->getResourceType($route_name, $route);
      $entity_type_id = $resource_type->getEntityTypeId();
      $bundle_name = $resource_type->getBundle();
      if (!$this->includeEntityTypeBundle($entity_type_id, $bundle_name)) {
        continue;
      }
      $api_path = [];
      $methods = $route->getMethods();
      foreach ($methods as $method) {
        $method = strtolower($method);
        $path_method = [];
        $path_method['summary'] = $this->getRouteMethodSummary($route, $route_name, $method);
        $path_method['description'] = '@todo Add descriptions';
        $path_method['parameters'] = $this->getMethodParameters($route, $resource_type, $method);
        $path_method['tags'] = [$this->getBundleTag($entity_type_id, $bundle_name)];
        $path_method['responses'] = $this->getEntityResponses($entity_type_id, $method, $bundle_name, $route_name);
        /*
         * @TODO: #2977109 - Calculate oauth scopes required.
         *
         * if (array_key_exists('oauth2', $path_method['security'])) {
         *   ...
         * }
         */

        $api_path[$method] = $path_method;
      }
      // Each path contains the "base path" from a OpenAPI perspective.
      $path = str_replace($this->getJsonApiBase(), '', $route->getPath());
      $api_paths[$path] = $api_path;
    }
    return $api_paths;
  }

  /**
   * Gets the JSON API routes.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   The routes.
   */
  protected function getJsonApiRoutes() {
    $all_routes = $this->routingProvider->getAllRoutes();
    $jsonapi_routes = [];
    $jsonapi_base_path = $this->getJsonApiBase();
    /** @var \Symfony\Component\Routing\Route $route */
    foreach ($all_routes as $route_name => $route) {
      if (!$route->getDefault(JsonApiRoutes::JSON_API_ROUTE_FLAG_KEY) || $route->getPath() == $jsonapi_base_path) {
        continue;
      }
      $jsonapi_routes[$route_name] = $route;
    }
    return $jsonapi_routes;
  }

  /**
   * Gets description of a method on a route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route.
   * @param string $route_name
   *   The route name.
   * @param string $method
   *   The method.
   *
   * @return string
   *   The method summary.
   */
  protected function getRouteMethodSummary(Route $route, $route_name, $method) {
    // @todo Make a better summary.
    if ($route_type = $this->getRoutTypeFromName($route_name)) {
      return "$route_type $method";
    }
    return '@todo';

  }

  /**
   * Gets the route from the name if possible.
   *
   * @param string $route_name
   *   The route name.
   *
   * @return string
   *   The route type.
   */
  protected function getRoutTypeFromName($route_name) {
    $route_name_parts = explode('.', $route_name);
    return isset($route_name_parts[2]) ? $route_name_parts[2] : '';
  }

  /**
   * Get the parameters array for a method on a route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON API resource type.
   * @param string $method
   *   The HTTP method.
   *
   * @return array
   *   The parameters.
   */
  protected function getMethodParameters(Route $route, ResourceType $resource_type, $method) {
    $parameters = [];
    $entity_type_id = $resource_type->getEntityTypeId();
    $bundle_name = $resource_type->getBundle();
    $option_parameters = $route->getOption('parameters');
    if (!empty($option_parameters) && $filtered_parameters = $this->filterParameters($option_parameters)) {
      foreach ($filtered_parameters as $parameter_name => $parameter_info) {
        $parameter = [
          'name' => $parameter_name,
          'required' => TRUE,
          'in' => 'path',
        ];
        if ($parameter_info['converter'] == static::JSON_API_UUID_CONVERTER) {
          $parameter['type'] = 'uuid';
          $parameter['description'] = $this->t('The uuid of the @entity @bundle',
            [
              '@entity' => $entity_type_id,
              '@bundle' => $bundle_name,
            ]
          );
        }
        $parameters[] = $parameter;
      }

      if ($this->jsonApiPathHasRelated($route->getPath())) {
        $parameters[] = [
          'name' => 'related',
          'required' => TRUE,
          'in' => 'path',
          'type' => 'string',
          'description' => $this->t('The relationship field name'),
        ];
      }
    }
    else {
      if ($method == 'get') {
        // If no route parameters and GET then this is collection route.
        // @todo Add descriptions or link to documentation.
        $parameters[] = [
          'name' => 'filter',
          'in' => 'query',
          'type' => 'array',
          'required' => FALSE,
          // 'description' => '@todo Explain filtering: https://www.drupal.org/docs/8/modules/json-api/collections-filtering-sorting-and-paginating',
        ];
        $parameters[] = [
          'name' => 'sort',
          'in' => 'query',
          'type' => 'array',
          'required' => FALSE,
          // 'description' => '@todo Explain sorting: https://www.drupal.org/docs/8/modules/json-api/collections-filtering-sorting-and-paginating',
        ];
        $parameters[] = [
          'name' => 'page',
          'in' => 'query',
          'type' => 'array',
          'required' => FALSE,
          // 'description' => '@todo Explain sorting: https://www.drupal.org/docs/8/modules/json-api/collections-filtering-sorting-and-paginating',
        ];
      }
      elseif ($method == 'post' || $method == 'patch') {
        // Determine if it is ContentEntity.
        if ($this->entityTypeManager->getDefinition($entity_type_id) instanceof ContentEntityTypeInterface) {
          $parameters[] = [
            'name' => 'body',
            'in' => 'body',
            'description' => $this->t('The @label object', ['@label' => $entity_type_id]),
            'required' => TRUE,
            'schema' => [
              '$ref' => $this->getDefinitionReference($entity_type_id, $bundle_name),
            ],
          ];
        }

      }
    }
    return $parameters;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityResponses($entity_type_id, $method, $bundle_name = NULL, $route_name = NULL) {
    $route_type = $this->getRoutTypeFromName($route_name);
    if ($route_type === 'collection') {
      if ($method === 'get') {
        $schema_response = [];
        if ($definition_ref = $this->getDefinitionReference($entity_type_id, $bundle_name)) {
          $schema_response = [
            'schema' => [
              'type' => 'array',
              'items' => [
                '$ref' => $definition_ref,
              ],
            ],
          ];
        }
        $responses['200'] = [
          'description' => 'successful operation',
        ] + $schema_response;
        return $responses;
      }

    }
    else {
      return parent::getEntityResponses($entity_type_id, $method, $bundle_name);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    static $definitions = [];
    if (!$definitions) {
      foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
        if ($entity_type instanceof ContentEntityTypeInterface) {
          if ($bundle_type = $entity_type->getBundleEntityType()) {
            $bundle_storage = $this->entityTypeManager->getStorage($bundle_type);
            $bundles = $bundle_storage->loadMultiple();
            foreach ($bundles as $bundle_name => $bundle) {
              if ($this->includeEntityTypeBundle($entity_type->id(), $bundle_name)) {
                $definitions[$this->getEntityDefinitionKey($entity_type->id(), $bundle_name)] = $this->getJsonSchema('api_json', $entity_type->id(), $bundle_name);
              }
            }
          }
          else {
            if ($this->includeEntityTypeBundle($entity_type->id())) {
              $definitions[$this->getEntityDefinitionKey($entity_type->id())] = $this->getJsonSchema('api_json', $entity_type->id());
            }
          }
        }
      }
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getTags() {
    $tags = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      if ($bundle_type_id = $entity_type->getBundleEntityType()) {
        $bundle_storage = $this->entityTypeManager->getStorage($bundle_type_id);
        $bundles = $bundle_storage->loadMultiple();
        foreach ($bundles as $bundle_name => $bundle) {
          if (!$this->includeEntityTypeBundle($entity_type->id(), $bundle_name)) {
            continue;
          }
          $description = $this->t("@bundle_label @bundle of type @entity_type.",
            [
              '@bundle_label' => $entity_type->getBundleLabel(),
              '@bundle' => $bundle->label(),
              '@entity_type' => $entity_type->getLabel(),
            ]
          );
          $tag = [
            'name' => $this->getBundleTag($entity_type->id(), $bundle->id()),
            'description' => $description,
            'x-entity-type' => $entity_type->id(),
            'x-definition' => [
              '$ref' => $this->getDefinitionReference($entity_type->id(), $bundle_name),
            ],
          ];
          if (method_exists($bundle, 'getDescription')) {
            $tag['description'] .= ' ' . $bundle->getDescription();
          }
          $tags[] = $tag;
        }
      }
      else {
        if (!$this->includeEntityTypeBundle($entity_type->id())) {
          continue;
        }
        $tag = [
          'name' => $this->getBundleTag($entity_type->id()),
        ];
        if ($entity_type instanceof ConfigEntityTypeInterface) {
          $tag['description'] = $this->t('Configuration entity @entity_type', ['@entity_type' => $entity_type->getLabel()]);
        }
        $tags[] = $tag;
      }
    }
    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getConsumes() {
    return [
      'application/vnd.api+json',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getProduces() {
    return [
      'application/vnd.api+json',
    ];
  }

  /**
   * Get the tag to use for a bundle.
   *
   * @param string $entity_type_id
   *   The entity type.
   * @param string $bundle_name
   *   The entity type.
   *
   * @return string
   *   The bundle tag.
   */
  protected function getBundleTag($entity_type_id, $bundle_name = NULL) {
    static $tags = [];
    if (!isset($tags[$entity_type_id][$bundle_name])) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $tag = $entity_type->getLabel();
      if ($bundle_name && $bundle_type_id = $entity_type->getBundleEntityType()) {
        $bundle_entity = $this->entityTypeManager->getStorage($bundle_type_id)->load($bundle_name);
        $tag .= ' - ' . $bundle_entity->label();
      }
      $tags[$entity_type_id][$bundle_name] = $tag;
    }
    return $tags[$entity_type_id][$bundle_name];
  }

  /**
   * {@inheritdoc}
   */
  public function getApiName() {
    return $this->t('JSON API');
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityDefinitionKey($entity_type_id, $bundle_name = NULL) {
    // Override the default definition key structure to use 'type--bundle'.
    if (!$bundle_name) {
      $bundle_name = $entity_type_id;
    }
    return parent::getEntityDefinitionKey($entity_type_id, $bundle_name);
  }

  /**
   * {@inheritdoc}
   */
  protected function getApiDescription() {
    return $this->t('This is a JSON API compliant implemenation');
  }

  /**
   * Gets a Resource Type.
   *
   * @param string $route_name
   *   The JSON API route name for which the ResourceType is wanted.
   * @param \Symfony\Component\Routing\Route $route
   *   The JSON API route for which the ResourceType is wanted.
   *
   * @return \Drupal\jsonapi\ResourceType\ResourceType
   *   Returns the ResourceType for the given JSON API route.
   */
  protected function getResourceType($route_name, Route $route) {
    $parameters[RouteObjectInterface::ROUTE_NAME] = $route_name;
    $parameters[RouteObjectInterface::ROUTE_OBJECT] = $route;
    $upcasted_parameters = $this->paramConverterManager->convert($parameters + $route->getDefaults());
    return $upcasted_parameters[JsonApiRoutes::RESOURCE_TYPE_KEY];
  }

  /**
   * Filters an associative array by key on a set of parameter.
   *
   * @param array $parameters
   *   Associative array that is going to be filtered.
   *
   * @return array
   *   Returns the filtered associative array.
   */
  protected function filterParameters(array $parameters) {
    foreach (static::$PARAMETERS_FILTER_LIST as $filter) {
      if (array_key_exists($filter, $parameters)) {
        unset($parameters[$filter]);
      }
    }
    return $parameters;
  }

  /**
   * Checks if a JSON API Path has {related}.
   *
   * @todo remove once https://www.drupal.org/project/jsonapi/issues/2953346
   * is done on JSON API Project.
   *
   * @param string $path
   *   The path.
   *
   * @return bool
   *   TRUE if path contains {related}, FALSE otherwise
   */
  protected function jsonApiPathHasRelated($path) {
    return strpos($path, '{related}') !== FALSE;
  }

}
