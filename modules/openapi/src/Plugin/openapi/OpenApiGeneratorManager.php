<?php

namespace Drupal\openapi\Plugin\openapi;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Defines interface for OpenApiGeneratorManager.
 *
 * @see \Drupal\openapi\Annotation\OpenApiGenerator.
 */
class OpenApiGeneratorManager extends DefaultPluginManager {

  /**
   * Constructs a GeneratorManager object.
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
      'Plugin/openapi/OpenApiGenerator',
      $namespaces,
      $module_handler,
      'Drupal\openapi\Plugin\openapi\OpenApiGeneratorInterface',
      'Drupal\openapi\Annotation\OpenApiGenerator'
    );
    $this->alterInfo('openapi_generator');
    $this->setCacheBackend($cache_backend, 'openapi_generator_plugins');
  }

  /**
   * {@inheritdoc}
   */
  protected function findDefinitions() {
    $definitions = parent::findDefinitions();
    foreach (['jsonapi', 'rest'] as $api_module) {
      if (isset($definitions[$api_module]) && !$this->moduleHandler->moduleExists($api_module)) {
        unset($definitions[$api_module]);
      }
    }
    return $definitions;
  }
}
