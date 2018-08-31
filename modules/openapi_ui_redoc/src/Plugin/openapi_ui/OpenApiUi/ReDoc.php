<?php

namespace Drupal\openapi_ui_redoc\Plugin\openapi_ui\OpenApiUi;

use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;

use Drupal\openapi_ui\Plugin\openapi_ui\OpenApiUi;

/**
 * Implements openapi_ui plugin for the swagger-ui library.
 *
 * @OpenApiUi(
 *   id = "redoc",
 *   label = @Translation("ReDoc"),
 * )
 */
class ReDoc extends OpenApiUi {

  /**
   * {@inheritdoc}
   */
  public function build(array $render_element) {
    $schema = $render_element['#openapi_schema'];
    $build = [
      '#type' => 'html_tag',
      '#tag' => 'redoc',
      '#attributes' => [
        'id' => 'redoc-ui',
      ],
      '#attached' => [
        'library' => [
          'openapi_ui_redoc/redoc',
        ],
      ],
    ];
    if ($schema instanceof Url) {
      $build['#attributes']['spec-url'] = $schema->toString();
    }
    else {
      $build['#attributes']['spec'] = Json::encode($schema);
      // We need to shim the redoc library to load from the spec attribute.
      $build['#attached']['library'][] = 'openapi_ui_redoc/redoc_attr';
    }
    return $build;
  }

}
