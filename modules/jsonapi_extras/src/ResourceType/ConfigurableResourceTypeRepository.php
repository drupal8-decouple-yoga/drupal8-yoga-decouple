<?php

namespace Drupal\jsonapi_extras\ResourceType;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepository;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;

/**
 * Provides a repository of JSON API configurable resource types.
 */
class ConfigurableResourceTypeRepository extends ResourceTypeRepository {

  /**
   * {@inheritdoc}
   */
  const RESOURCE_TYPE_CLASS = ConfigurableResourceType::class;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Plugin manager for enhancers.
   *
   * @var \Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerManager
   */
  protected $enhancerManager;

  /**
   * The bundle manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * A list of all resource types.
   *
   * @var \Drupal\jsonapi_extras\ResourceType\ConfigurableResourceType[]
   */
  protected $resourceTypes;

  /**
   * A list of only enabled resource types.
   *
   * @var \Drupal\jsonapi_extras\ResourceType\ConfigurableResourceType[]
   */
  protected $enabledResourceTypes;

  /**
   * A list of all resource configuration entities.
   *
   * @var \Drupal\jsonapi_extras\Entity\JsonapiResourceConfig[]
   */
  protected $resourceConfigs;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $bundle_manager, EntityFieldManagerInterface $entity_field_manager, EntityRepositoryInterface $entity_repository, ResourceFieldEnhancerManager $enhancer_manager, ConfigFactoryInterface $config_factory) {
    parent::__construct($entity_type_manager, $bundle_manager, $entity_field_manager);
    $this->entityRepository = $entity_repository;
    $this->enhancerManager = $enhancer_manager;
    $this->configFactory = $config_factory;
    $this->entityFieldManager = $entity_field_manager;
    $this->bundleManager = $bundle_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected static function isMutableResourceType(EntityTypeInterface $entity_type) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function all() {
    if (!$this->all) {
      $all = parent::all();
      array_walk($all, [$this, 'injectAdditionalServicesToResourceType']);
      $this->all = $all;
    }
    return $this->all;
  }

  /**
   * Injects a additional services into the configurable resource type.
   *
   * @param \Drupal\jsonapi_extras\ResourceType\ConfigurableResourceType $resource_type
   *   The resource type.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function injectAdditionalServicesToResourceType(ConfigurableResourceType $resource_type) {
    $resource_config_id = sprintf(
      '%s--%s',
      $resource_type->getEntityTypeId(),
      $resource_type->getBundle()
    );
    $resource_config = $this->getResourceConfig($resource_config_id);
    $resource_type->setJsonapiResourceConfig($resource_config);
    $resource_type->setEnhancerManager($this->enhancerManager);
    $resource_type->setConfigFactory($this->configFactory);
    $entity_type = $this
      ->entityTypeManager
      ->getDefinition($resource_type->getEntityTypeId());
    $is_internal = static:: shouldBeInternalResourceType($entity_type)
      || (bool) $resource_config->get('disabled');
    $resource_type->setInternal($is_internal);
  }

  /**
   * Get a single resource configuration entity by its ID.
   *
   * @param string $resource_config_id
   *   The configuration entity ID.
   *
   * @return \Drupal\jsonapi_extras\Entity\JsonapiResourceConfig
   *   The configuration entity for the resource type.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getResourceConfig($resource_config_id) {
    $resource_configs = $this->getResourceConfigs();
    return isset($resource_configs[$resource_config_id]) ?
      $resource_configs[$resource_config_id] :
      new NullJsonapiResourceConfig([], '');
  }

  /**
   * Load all resource configuration entities.
   *
   * @return \Drupal\jsonapi_extras\Entity\JsonapiResourceConfig[]
   *   The resource config entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getResourceConfigs() {
    if (!$this->resourceConfigs) {
      $resource_config_ids = [];
      foreach ($this->getEntityTypeBundleTuples() as $tuple) {
        list($entity_type_id, $bundle) = $tuple;
        $resource_config_ids[] = sprintf('%s--%s', $entity_type_id, $bundle);
      }
      $this->resourceConfigs = $this->entityTypeManager
        ->getStorage('jsonapi_resource_config')
        ->loadMultiple($resource_config_ids);
    }
    return $this->resourceConfigs;
  }

  /**
   * Entity type ID and bundle iterator.
   *
   * @return array
   *   A list of entity type ID and bundle tuples.
   */
  protected function getEntityTypeBundleTuples() {
    $entity_type_ids = array_keys($this->entityTypeManager->getDefinitions());
    // For each entity type return as many tuples as bundles.
    return array_reduce($entity_type_ids, function ($carry, $entity_type_id) {
      $bundles = array_keys($this->bundleManager->getBundleInfo($entity_type_id));
      // Get all the tuples for the current entity type.
      $tuples = array_map(function ($bundle) use ($entity_type_id) {
        return [$entity_type_id, $bundle];
      }, $bundles);
      // Append the tuples to the aggregated list.
      return array_merge($carry, $tuples);
    }, []);
  }

  /**
   * Resets the internal caches for resource types and resource configs.
   */
  public function reset() {
    $this->all = NULL;
    $this->resourceConfigs = NULL;
  }

}
