<?php

namespace Drupal\Tests\openapi\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\openapi_test\Entity\OpenApiTestEntityType;
use Drupal\rest\Entity\RestResourceConfig;
use Drupal\rest\RestResourceConfigInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests requests OpenAPI routes.
 *
 * @group OpenAPI
 */
class RequestTest extends BrowserTestBase {

  /**
   * Set to TRUE to run this test to generate expectation files.
   *
   * The test will be marked as a fail when generating test files.
   */
  const GENERATE_EXPECTATION_FILES = FALSE;

  /**
   * List of required array keys for response schema.
   */
  const EXPECTED_STRUCTURE = [
    'swagger' => 'swagger',
    'schemes' => 'schema',
    'info' => [
      'description' => 'description',
      'version' => 'version',
      'title' => 'title',
    ],
    'host' => 'host',
    'basePath' => 'basePath',
    'securityDefinitions' => 'securityDefinitions',
    'tags' => 'tags',
    'definitions' => 'definitions',
    'paths' => 'paths',
    'consumes' => 'consumes',
    'produces' => 'produces',
  ];

  const ENTITY_TEST_BUNDLES = [
    "taxonomy_term" => [
      "camelids",
      "taxonomy_term_test",
    ],
    "openapi_test_entity" => [
      "camelids",
      "openapi_test_entity_test",
    ],
    "openapi_test_entity_type" => [],
    "user" => [],
  ];

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'user',
    'field',
    'filter',
    'text',
    'taxonomy',
    'serialization',
    'hal',
    'schemata',
    'schemata_json_schema',
    'openapi',
    'rest',
    'openapi_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    /*
     * @TODO: The below configuration/setup should be shipped as part of the
     * test resources sub module.
     */
    foreach (static::ENTITY_TEST_BUNDLES['taxonomy_term'] as $bundle) {
      if (!Vocabulary::load($bundle)) {
        // Create a new vocabulary.
        $vocabulary = Vocabulary::create([
          'name' => $bundle,
          'vid' => $bundle,
        ]);
        $vocabulary->save();
      }
    }
    foreach (static::ENTITY_TEST_BUNDLES['openapi_test_entity'] as $bundle) {
      if (!OpenApiTestEntityType::load($bundle)) {
        // Create a new bundle.
        OpenApiTestEntityType::create([
          'label' => $bundle,
          'id' => $bundle,
        ])->save();
      }
    }

    foreach (array_filter(static::ENTITY_TEST_BUNDLES) as $entity_type => $bundles) {
      // Add single value and multi value fields.
      FieldStorageConfig::create([
        'entity_type' => $entity_type,
        'field_name' => 'field_test_' . $entity_type,
        'type' => 'text',
      ])
        ->setCardinality(1)
        ->save();
      foreach ($bundles as $bundle) {
        // Add field to each bundle.
        FieldConfig::create([
          'entity_type' => $entity_type,
          'field_name' => 'field_test_' . $entity_type,
          'bundle' => $bundle,
        ])
          ->setLabel('Test field')
          ->setTranslatable(FALSE)
          ->save();
      }
    }

    $this->drupalLogin($this->drupalCreateUser([
      'access openapi api docs',
      'access content',
    ]));
  }

  /**
   * Tests OpenAPI requests.
   *
   * @dataProvider providerRequestTypes
   */
  public function testRequests($api_module, $options = []) {
    if ($api_module == 'rest') {
      // Enable all the entity types each request to make sure $options is
      // respected for all parts of the spec.
      $enable_entity_types = [
        'openapi_test_entity' => ['GET', 'POST', 'PATCH', 'DELETE'],
        'openapi_test_entity_type' => ['GET'],
        'user' => ['GET'],
        'taxonomy_term' => ['GET', 'POST', 'PATCH', 'DELETE'],
        'taxonomy_vocabulary' => ['GET'],
      ];
      foreach ($enable_entity_types as $entity_type_id => $methods) {
        foreach ($methods as $method) {
          $this->enableRestService("entity:$entity_type_id", $method, 'json');
          if ($entity_type_id === 'openapi_test_entity') {
            $this->enableRestService("entity:$entity_type_id", $method, 'hal_json');
          }
        }
      }
      $this->container->get('router.builder')->rebuild();
    }

    if ($api_module == 'jsonapi') {
      // @todo Add JSON API to $modules
      // Currently this will not work because the new bundles are not picked
      // up in \Drupal\jsonapi\Routing\Routes::routes().
      $this->container->get('module_installer')->install(['jsonapi']);
    }

    $this->requestOpenApiJson($api_module, $options);
  }

  /**
   * Assert that test expectation generation is disabled.
   */
  public function testNotGenerating() {
    $this->assertFalse(static::GENERATE_EXPECTATION_FILES, 'Expectation files generated. Change \Drupal\Tests\openapi\Functional\RequestTest::GENERATE_EXPECTATION_FILES to FALSE to run tests.');
  }

  /**
   * Dataprovider for testRequests.
   */
  public function providerRequestTypes() {
    $data = [];
    foreach (['rest', 'jsonapi'] as $api_module) {
      foreach (static::ENTITY_TEST_BUNDLES as $entity_type => $bundles) {
        foreach ($bundles as $bundle) {
          $data[$api_module . ':' . $entity_type . '_' . $bundle] = [
            $api_module,
            [
              'entity_type_id' => $entity_type,
              'bundle_name' => $bundle,
            ],
          ];
        }
        // Test all bundles for entity type.
        $data[$api_module . ':' . $entity_type] = [
          $api_module,
          [
            'entity_type_id' => $entity_type,
          ],
        ];
      }
      // Test all entity types and bundle for module.
      $data[$api_module] = [$api_module];
    }
    return $data;
  }

  /**
   * Makes OpenAPI request and checks the response.
   *
   * @param string $api_module
   *   The API module being tested. Either 'rest' or 'jsonapi'.
   * @param array $options
   *   The query options for generating the OpenAPI output.
   */
  protected function requestOpenApiJson($api_module, array $options = []) {
    $get_options = [
      'query' => [
        '_format' => 'json',
        'options' => $options,
      ],
    ];
    $response = $this->drupalGet("openapi/$api_module", $get_options);
    $decoded_response = json_decode($response, TRUE);
    $this->assertSession()->statusCodeEquals(200);

    // Test the the first tier schema has the expected keys.
    $structure_keys = array_keys(static::EXPECTED_STRUCTURE);
    $response_keys = array_keys($decoded_response);
    $missing = array_diff($structure_keys, $response_keys);
    $this->assertTrue(empty($missing), 'Schema missing expected key(s): ' . implode(', ', $missing));

    // Test that the required info block keys exist in the response.
    $structure_info_keys = array_keys(static::EXPECTED_STRUCTURE['info']);
    $response_keys = array_keys($decoded_response['info']);
    $missing_info = array_diff($structure_info_keys, $response_keys);
    $this->assertTrue(empty($missing_info), 'Schema info missing expected key(s): ' . implode(', ', $missing_info));

    // Test that schemes is not empty.
    $this->assertTrue(!empty($decoded_response['schemes']), 'Schema for ' . $api_module . ' should define at least one scheme.');

    // Test basePath and host.
    $port = parse_url($this->baseUrl, PHP_URL_PORT);
    $host = parse_url($this->baseUrl, PHP_URL_HOST) . ($port ? ':' . $port : '');
    $this->assertEquals($host, $decoded_response['host'], 'Schema has invalid host.');
    $basePath = $this->getBasePath();
    $response_basePath = $decoded_response['basePath'];
    $this->assertEquals($basePath, substr($response_basePath, 0, strlen($basePath)), 'Schema has invalid basePath.');
    $routeBase = ($api_module === 'jsonapi') ? 'jsonapi' : '';
    $response_routeBase = substr($response_basePath, strlen($basePath));
    // Verify that with the subdirectory removed, that the basePath is correct.
    $this->assertEquals($routeBase, ltrim($response_routeBase, '/'), 'Route base path is invalid.');

    // Verify that root consumes and produces exists and is not empty.
    foreach (['consumes', 'produces'] as $key) {
      $this->assertArrayHasKey($key, $decoded_response, "Schema does not contains a root $key");
      $this->assertNotEmpty($decoded_response[$key], "Schema has empty root $key");
      if (!isset($decoded_response[$key])) {
        if ($api_module == 'jsonapi') {
          $this->assertEquals(['application/vnd.api+json'], $decoded_response[$key], "$api_module root $key should only contain application/vnd.api+json");
        }
        elseif ($api_module == 'rest') {
          $rest_mimetypes = ['application/json'];
          if (isset($options['entity_type_id']) && $options['entity_type_id'] === 'openapi_test_entity') {
            $rest_mimetypes[] = 'application/hal+json';
          }
          $this->assertEquals($rest_mimetypes, $decoded_response[$key], "$api_module root $key should only contain " . implode(' and ', $rest_mimetypes));
        }
      }
    }

    /*
     * Tags for rest schema define 'x-entity-type' to reference the entity type
     * associated with the entity. This value should exist in the definitions.
     *
     * NOTE: Currently not all entity types are provided as definitions. As a
     * result, the below test is subject to failure, and has been disabled.
     *
     * @TODO: #2940397 - Convert x-entity-type to x-definition.
     * @TODO: #2940407 - Provide all entity types as definitions.
     */
    $tags = $decoded_response['tags'];
    if (FALSE) {
      $definitions = $decoded_response['definitions'];
      foreach ($tags as $tag) {
        if (isset($tag['x-entity-type'])) {
          $type_id = $tag['x-entity-type'];
          $this->assertTrue(isset($definitions[$type_id]), 'The \'x-entity-type\' ' . $type_id . ' is invalid for ' . $tag['name'] . '.');
        }
      }
    }

    // Validate that all security definitions are valid, and have a provider.
    $security_definitions = $decoded_response['securityDefinitions'];
    $auth_providers = $this->container->get('authentication_collector')->getSortedProviders();
    $supported_security_types = ['basic', 'apiKey', 'cookie', 'oauth', 'oauth2'];
    foreach ($security_definitions as $definition_id => $definition) {
      if ($definition_id !== 'csrf_token') {
        // CSRF Token will never have an auth collector, all others shoud.
        $this->assertTrue(array_key_exists($definition_id, $auth_providers), 'Security definition ' . $definition_id . ' not an auth collector.');
      }
      $this->assertTrue(in_array($definition['type'], $supported_security_types), 'Security definition schema ' . $definition_id . ' has invalid type '. $definition['type']);
    }

    // Test paths for valid tags, schema, security, and definitions.
    $paths = &$decoded_response['paths'];
    $tag_names = array_column($tags, 'name');
    $all_method_tags = [];
    foreach ($paths as $path => &$methods) {
      foreach ($methods as $method => &$method_schema) {
        // Ensure all tags are defined.
        $missing_tags = array_diff($method_schema['tags'], $tag_names);
        $all_method_tags = array_merge($all_method_tags, $method_schema['tags']);
        $this->assertTrue(empty($missing_tags), 'Method ' . $method . ' for ' . $path . ' has invalid tag(s): ' . implode(', ', $missing_tags));

        // Ensure all request schemes are defined.
        if (isset($method_schema['schemes'])) {
          $missing_schemas = array_diff($method_schema['schemes'], $decoded_response['schemes']);
          $this->assertTrue(empty($missing_schemas), 'Method ' . $method . ' for ' . $path . ' has invalid scheme(s): ' . implode(', ', $missing_schemas));
        }

        $response_security_types = array_keys($decoded_response['securityDefinitions']);
        if (isset($method_schema['security'])) {
          foreach ($method_schema['security'] as $security_definitions) {
            $security_types = array_keys($security_definitions);
            $missing_security_types = array_diff($security_types, $response_security_types);
            $this->assertTrue(empty($missing_security_types), 'Method ' . $method . ' for ' . $path . ' has invalid security type(s): ' . implode(', ', $missing_security_types) . ' + ' . implode(', ', $security_types) . ' + ' . implode(', ', $response_security_types));
          };
        }

        foreach (['consumes', 'produces'] as $key) {
          if (isset($method_schema[$key]) && !empty($method_schema[$key])) {
            // Filter out mimetypes that exist in parent.
            $method_extra_mimetypes = array_diff($method_schema[$key], $decoded_response[$key]);
            $this->assertEmpty($method_extra_mimetypes, 'Method ' . $method . ' for ' . $path . ' has invalid mime type(s): ' . implode(', ', $method_extra_mimetypes));

            if ($api_module == 'rest') {
              $rest_mimetypes = ['application/json'];
              if (isset($options['entity_type_id']) && $options['entity_type_id'] === 'openapi_test_entity') {
                $rest_mimetypes[] = 'application/hal+json';
              }
              $this->assertEquals($rest_mimetypes, $method_schema[$key], 'Entity type ' . $options['entity_type_id'] . ' should only have REST mimetype(s): ' . implode(', ', $rest_mimetypes));
            }
          }
        }

        // Remove all tested properties from method schema.
        unset($method_schema['tags']);
        unset($method_schema['schemes']);
        unset($method_schema['security']);
      }
    }
    $all_method_tags = array_unique($all_method_tags);
    asort($all_method_tags);
    asort($tag_names);
    $this->assertEquals(array_values($all_method_tags), array_values($tag_names), "Method tags equal tag names");

    // Strip response down to only untested properties.
    $root_keys = ['definitions', 'paths'];
    foreach (array_diff(array_keys($decoded_response), $root_keys) as $remove) {
      unset($decoded_response[$remove]);
    }

    // Build file name.
    $file_name = __DIR__ . "/../../expectations/$api_module";
    if ($options) {
      $file_name .= "." . implode('.', $options);
    }
    $file_name .= '.json';
    if (static::GENERATE_EXPECTATION_FILES) {
      $this->saveExpectationFile($file_name, $decoded_response);
      // Response assertion is not performed when generating expectation
      // files.
      return;
    }
    // Load expected value and test remaining schema.
    $expected = json_decode(file_get_contents($file_name), TRUE);

    $this->nestedKsort($expected);
    $this->nestedKsort($decoded_response);
    $this->assertEquals($expected, $decoded_response, "The response does not match expected file: $file_name");
  }

  /**
   * Saves an expectation file.
   *
   * @param string $file_name
   *   The file name of the expectation file.
   * @param array $decoded_response
   *   The decoded JSON response.
   *
   * @see \Drupal\Tests\openapi\Functional\RequestTest::GENERATE_EXPECTATION_FILES
   */
  protected function saveExpectationFile($file_name, array $decoded_response) {
    // Remove the base path from the start of the string, if present.
    $re_encode = json_encode($decoded_response, JSON_PRETTY_PRINT);
    file_put_contents($file_name, $re_encode);
  }

  /**
   * Enables the REST service interface for a specific entity type.
   *
   * @param string|false $resource_type
   *   The resource type that should get REST API enabled or FALSE to disable
   *   all resource types.
   * @param string $method
   *   The HTTP method to enable, e.g. GET, POST etc.
   * @param string|array $format
   *   (Optional) The serialization format, e.g. hal_json, or a list of formats.
   * @param array $auth
   *   (Optional) The list of valid authentication methods.
   */
  protected function enableRestService($resource_type, $method = 'GET', $format = 'json', array $auth = ['csrf_token']) {
    if ($resource_type) {
      // Enable REST API for this entity type.
      $resource_config_id = str_replace(':', '.', $resource_type);
      // Get entity by id.
      /** @var \Drupal\rest\RestResourceConfigInterface $resource_config */
      $resource_config = RestResourceConfig::load($resource_config_id);
      if (!$resource_config) {
        $resource_config = RestResourceConfig::create([
          'id' => $resource_config_id,
          'granularity' => RestResourceConfigInterface::METHOD_GRANULARITY,
          'configuration' => [],
        ]);
      }
      $configuration = $resource_config->get('configuration');

      if (is_array($format)) {
        for ($i = 0; $i < count($format); $i++) {
          $configuration[$method]['supported_formats'][] = $format[$i];
        }
      }
      else {

        $configuration[$method]['supported_formats'][] = $format;
      }

      foreach ($auth as $auth_provider) {
        $configuration[$method]['supported_auth'][] = $auth_provider;
      }

      $resource_config->set('configuration', $configuration);
      $resource_config->save();
    }
    else {
      foreach (RestResourceConfig::loadMultiple() as $resource_config) {
        $resource_config->delete();
      }
    }
  }

  /**
   * Gets the base path to be used in OpenAPI.
   *
   * @return string
   *   The base path.
   */
  protected function getBasePath() {
    $path = rtrim(parse_url($this->baseUrl, PHP_URL_PATH), '/');

    // OpenAPI spec states that the base path must start with a '/'.
    if (strlen($path) == 0) {
      // For a zero length string, set it to minimal value.
      $path = "/";
    }
    elseif (substr($path, 0, 1) !== '/') {
      // Prepend a slash to any other string that don't have one.
      $path = '/' . $path;
    }
    return $path;
  }

  /**
   * Sorts a nested array with ksort().
   *
   * @param array $array
   *   The nested array to sort.
   */
  protected function nestedKsort(array &$array) {
    if ($this->isAssocArray($array)) {
      ksort($array);
    }
    else {
      usort($array, function ($a, $b) {
        if (isset($a['name']) && isset($b['name'])) {
          return strcmp($a['name'], $b['name']);
        }
        return -1;
      });
    }

    foreach ($array as &$item) {
      if (is_array($item)) {
        $this->nestedKsort($item);
      }
    }
  }

  /**
   * Determine if an array is associative array.
   *
   * @param array $arr
   *   The array.
   *
   * @return bool
   *   TRUE if the array is associative, otherwise false.
   */
  protected function isAssocArray(array $arr) {
    if (empty($arr)) {
      return FALSE;
    }
    return array_keys($arr) !== range(0, count($arr) - 1);
  }

}
