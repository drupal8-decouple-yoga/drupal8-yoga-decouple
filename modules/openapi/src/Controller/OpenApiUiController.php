<?php

namespace Drupal\openapi\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

use Drupal\openapi\Plugin\openapi\OpenApiGeneratorInterface;
use Drupal\openapi_ui\Plugin\openapi_ui\OpenApiUiInterface;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API Specification controller base.
 */
class OpenApiUiController extends ControllerBase {

  /**
   * Gets the OpenAPI output in JSON format.
   *
   * @return string
   *   The page title.
   */
  public function title(OpenApiUiInterface $openapi_ui, OpenApiGeneratorInterface $generator, Request $request) {
    return $this->t(
      '%label OpenApi Docs UI',
      [
        '%label' => $generator->getLabel(),
      ]
    );
  }

  /**
   * Gets the OpenAPI output in JSON format.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function generate(OpenApiUiInterface $openapi_ui, OpenApiGeneratorInterface $openapi_generator, Request $request) {
    $options = $request->get('options', []);
    $build = [
      '#type' => 'openapi_ui',
      '#openapi_ui_plugin' => $openapi_ui,
      '#openapi_schema' => $openapi_generator->getSpecification(),
    //  '#openapi_schema' => Url::fromRoute('openapi.download', ['openapi_generator' => $openapi_generator->getPluginId()], ['query' => ['_format' => 'json', 'options' => $options]]),
    ];
    return $build;
  }

}
