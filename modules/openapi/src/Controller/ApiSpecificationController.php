<?php

namespace Drupal\openapi\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\openapi\Plugin\openapi\OpenApiGeneratorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API Specification controller base.
 */
class ApiSpecificationController extends ControllerBase {

  /**
   * Gets the OpenAPI output in JSON format.
   *
   * @return string
   *   The page title.
   */
  public function title(OpenApiGeneratorInterface $generator, Request $request) {
    return $this->t(
      '%label OpenApi Schema Download',
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
  public function getSpecification(OpenApiGeneratorInterface $openapi_generator, Request $request) {
    $options = $request->get('options', []);
    $openapi_generator->setOptions($options);
    $spec = $openapi_generator->getSpecification();
    return new JsonResponse($spec);
  }

}
