<?php

namespace Drupal\openapi_test\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for defining OpenApi Test Entity entities.
 *
 * @ingroup openapi_test
 */
interface OpenApiTestEntityInterface extends ContentEntityInterface {

  /**
   * Gets the OpenApi Test Entity name.
   *
   * @return string
   *   Name of the OpenApi Test Entity.
   */
  public function getName();

  /**
   * Sets the OpenApi Test Entity name.
   *
   * @param string $name
   *   The OpenApi Test Entity name.
   *
   * @return \Drupal\openapi_test\Entity\OpenApiTestEntityInterface
   *   The called OpenApi Test Entity entity.
   */
  public function setName($name);

}
