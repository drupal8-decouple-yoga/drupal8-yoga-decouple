<?php

namespace Drupal\jsonapi_extras\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages discovery and instantiation of resourceFieldEnhancer plugins.
 */
class ResourceFieldEnhancerManager extends DefaultPluginManager {

  /**
   * Constructs a new ResourceFieldEnhancerManager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/jsonapi/FieldEnhancer',
      $namespaces,
      $module_handler,
      'Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerInterface',
      'Drupal\jsonapi_extras\Annotation\ResourceFieldEnhancer'
    );

    $this->alterInfo('resource_field_enhancer_info');
    $this->setCacheBackend($cache_backend, 'resource_field_enhancer_plugins');
  }

}
