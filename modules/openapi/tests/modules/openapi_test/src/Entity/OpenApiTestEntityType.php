<?php

namespace Drupal\openapi_test\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the OpenApi Test Entity type entity.
 *
 * @ConfigEntityType(
 *   id = "openapi_test_entity_type",
 *   label = @Translation("OpenApi Test Entity type"),
 *   config_prefix = "openapi_test_entity_type",
 *   admin_permission = "administer site configuration",
 *   bundle_of = "openapi_test_entity",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   }
 * )
 */
class OpenApiTestEntityType extends ConfigEntityBundleBase implements OpenApiTestEntityTypeInterface {

  /**
   * The OpenApi Test Entity type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The OpenApi Test Entity type label.
   *
   * @var string
   */
  protected $label;

}
