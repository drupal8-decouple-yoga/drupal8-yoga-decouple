<?php

namespace Drupal\jsonapi_include;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;


/**
 * Modifies the jsonapi normalizer service.
 */
class JsonapiIncludeServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {

    // Overrides jsonapi class to add custom overrides.
    /** @var \Symfony\Component\DependencyInjection\Definition $definition */
    $definition = $container->getDefinition('serializer.normalizer.jsonapi_document_toplevel.jsonapi');
    $definition->setClass('Drupal\jsonapi_include\Normalizer\JsonApiDocumentTopLevelNormalizer');
  }

}
