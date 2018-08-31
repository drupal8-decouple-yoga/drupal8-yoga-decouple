<?php

namespace Drupal\openapi\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an annotation object for OpenApiGenerator plugins.
 *
 * Plugin Namespace: Plugin\openapi\OpenApiGenerator.
 *
 * @see plugin_api
 *
 * @Annotation
 */
class OpenApiGenerator extends Plugin {

  /**
   * The plugin id.
   *
   * @var string
   */
  public $id;

  /**
   * The plugin label.
   *
   * @var string
   */
  public $label;

}
