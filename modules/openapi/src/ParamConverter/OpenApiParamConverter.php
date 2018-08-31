<?php

namespace Drupal\openapi\ParamConverter;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\ParamConverter\ParamConverterInterface;

use Symfony\Component\Routing\Route;

/**
 * Defines a ParamConverter for Openapi Plugins.
 */
class OpenApiParamConverter implements ParamConverterInterface {

  /**
   * Current openapi generator plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  public $openApiGeneratorManager;

  /**
   * Creates a new OpenApiParamConverter.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $open_api_generator_manager
   *   The current openapi generator plugin manager instance.
   */
  public function __construct(PluginManagerInterface $open_api_generator_manager) {
    $this->openApiGeneratorManager = $open_api_generator_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    try {
      $generator = $this->openApiGeneratorManager->createInstance($value);
    }
    catch (PluginNotFoundException $e) {
      // Plugin Not found, we can't convert it the param.
      return NULL;
    }
    return $generator;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return (!empty($definition['type']) && $definition['type'] == 'openapi_generator');
  }

}
