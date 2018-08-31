<?php

namespace Drupal\openapi\Plugin\openapi;

/**
 * Defines OpenApiGeneratorInterface for OpenApi Generator Plugins.
 */
interface OpenApiGeneratorInterface {

  /**
   * Set the options for the current schema download.
   *
   * @todo Document all options.
   *
   * @param array $options
   *   The options for the specification generation.
   *   - exclude: Array of Entity types or bundles to exclude in the format,
   *      "[ENTITY_TYPE]" or "[ENTITY_TYPE]:[BUNDLE]".
   */
  public function setOptions($options);

  /**
   * Get the options for the current schema download.
   *
   * @return array
   *   The options for generating the schema.
   */
  public function getOptions();

  /**
   * Get plugin id.
   *
   * @return string
   *   Plugin id.
   */
  public function getId();

  /**
   * Get plugin label.
   *
   * @return string
   *   Plugin label.
   */
  public function getLabel();

  /**
   * Get base path for schema.
   *
   * @return string
   *   String name with a leading slash.
   */
  public function getBasePath();

  /**
   * Returns a list of valid security types for the api.
   *
   * Values of returned array will be empty, except for OAuth2 definitions, for
   * which the required scopes should be returned.
   *
   * @return array
   *   An array where keys correspond to a security scheme.
   */
  public function getSecurity();

  /**
   * Get a list a valid security method definitions.
   *
   * Returned schema should be similar to the below structure.
   *
   * ```
   * {
   *   "api_key": {
   *     "type": "apiKey",
   *     "name": "api_key",
   *     "in": "header"
   *   },
   *   "petstore_auth": {
   *     "type": "oauth2",
   *     "authorizationUrl": "http://swagger.io/api/oauth/dialog",
   *     "flow": "implicit",
   *     "scopes": {
   *       "write:pets": "modify pets in your account",
   *       "read:pets": "read your pets"
   *     }
   *   }
   * }
   * ```
   *
   * @return array
   *   Associative array of security definitions.
   */
  public function getSecurityDefinitions();

  /**
   * Get tags for schema.
   *
   * @return array
   *   Schema tag list.
   */
  public function getTags();

  /**
   * Returns the paths information.
   *
   * @return array
   *   The info elements.
   */
  public function getPaths();

  /**
   * Generates OpenAPI specification.
   *
   * @return array
   *   The specification output.
   */
  public function getSpecification();

  /**
   * Get model definitions for Drupal entities and bundles.
   *
   * @return array
   *   The model definitions.
   */
  public function getDefinitions();

  /**
   * Get a list of all MIME Type that the API Consumes
   *
   * @return array
   *    An array of all MIME Type that the API Consumes
   */
  public function getConsumes();

  /**
   * Get a list of all MIME Type that the API Produces
   *
   * @return array
   *    An array of all MIME Type that the API Produces
   */
  public function getProduces();

}
