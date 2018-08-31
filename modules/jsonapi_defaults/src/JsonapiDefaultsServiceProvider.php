<?php

namespace Drupal\jsonapi_defaults;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Modifies the jsonapi normalizer service.
 */
class JsonapiDefaultsServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {

    // Overrides jsonapi class to add custom overrides.
    /** @var \Symfony\Component\DependencyInjection\Definition $definition */
    $definition = $container->getDefinition('serializer.normalizer.jsonapi_document_toplevel.jsonapi');
    $definition->setClass('Drupal\jsonapi_defaults\JsonApiDefaultsJsonApiDocumentTopLevelNormalizer');

    /** @var \Symfony\Component\DependencyInjection\Definition $definition */
    $definition = $container->getDefinition('jsonapi.params.enhancer');
    $definition->setClass('Drupal\jsonapi_defaults\JsonApiDefaultsJsonApiParamEnhancer');
    $definition->addArgument(new Reference('config.manager'));

  }

}
