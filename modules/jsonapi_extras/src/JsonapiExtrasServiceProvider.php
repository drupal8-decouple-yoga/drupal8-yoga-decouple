<?php

namespace Drupal\jsonapi_extras;

use Drupal\Core\Config\BootstrapConfigStorageFactory;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\jsonapi_extras\ResourceType\ConfigurableResourceTypeRepository;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Replace the resource type repository for our own configurable version.
 */
class JsonapiExtrasServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->has('jsonapi.resource_type.repository')) {
      // Override the class used for the configurable service.
      $definition = $container->getDefinition('jsonapi.resource_type.repository');
      $definition->setClass(ConfigurableResourceTypeRepository::class);
      // The configurable service expects the entity repository and the enhancer
      // plugin manager.
      $definition->addArgument(new Reference('entity.repository'));
      $definition->addArgument(new Reference('plugin.manager.resource_field_enhancer'));
      $definition->addArgument(new Reference('config.factory'));
    }

    $settings = BootstrapConfigStorageFactory::get()
      ->read('jsonapi_extras.settings');

    if ($settings !== FALSE) {
      $container->setParameter('jsonapi.base_path', '/' . $settings['path_prefix']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $modules = $container->getParameter(('container.modules'));

    if (isset($modules['schemata_json_schema'])) {
      // Register field definition schema override.
      $container
        ->register('serializer.normalizer.field_definition.schema_json.jsonapi_extras', 'Drupal\jsonapi_extras\Normalizer\SchemaFieldDefinitionNormalizer')
        ->addTag('normalizer', ['priority' => 32])
        ->addArgument(new Reference('jsonapi.resource_type.repository'));

      // Register top-level schema override.
      $container
        ->register('serializer.normalizer.schemata_schema_normalizer.schema_json.jsonapi_extras', 'Drupal\jsonapi_extras\Normalizer\SchemataSchemaNormalizer')
        ->addTag('normalizer', ['priority' => 100])
        ->addArgument(new Reference('jsonapi.resource_type.repository'));
    }
  }

}
