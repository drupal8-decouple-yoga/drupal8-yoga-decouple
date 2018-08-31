<?php

namespace Drupal\Tests\jsonapi_extras\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\jsonapi_extras\Entity\JsonapiResourceConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\jsonapi\Functional\JsonApiFunctionalTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Symfony\Component\Routing\Route;

/**
 * The test class for the main functionality.
 *
 * @group jsonapi_extras
 */
class JsonExtrasApiFunctionalTest extends JsonApiFunctionalTestBase {

  public static $modules = [
    'jsonapi_extras',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Add vocabs field to the tags.
    $this->createEntityReferenceField(
      'taxonomy_term',
      'tags',
      'vocabs',
      'Vocabularies',
      'taxonomy_vocabulary',
      'default',
      [
        'target_bundles' => [
          'tags' => 'taxonomy_vocabulary',
        ],
        'auto_create' => TRUE,
      ],
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );

    FieldStorageConfig::create([
      'field_name' => 'field_timestamp',
      'entity_type' => 'node',
      'type' => 'timestamp',
      'settings' => [],
      'cardinality' => 1,
    ])->save();

    $field_config = FieldConfig::create([
      'field_name' => 'field_timestamp',
      'label' => 'Timestamp',
      'entity_type' => 'node',
      'bundle' => 'article',
      'required' => FALSE,
      'settings' => [],
      'description' => '',
    ]);
    $field_config->save();


    $config = \Drupal::configFactory()->getEditable('jsonapi_extras.settings');
    $config->set('path_prefix', 'api');
    $config->set('include_count', TRUE);
    $config->save(TRUE);
    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), ['access jsonapi resource list']);
    static::overrideResources();
    $this->resetAll();
    $role = $this->user->get('roles')[0]->entity;
    $this->grantPermissions($role, ['administer nodes', 'administer site configuration']);
  }

  /**
   * {@inheritdoc}
   *
   * Appends the 'application/vnd.api+json' if there's no Accept header.
   */
  protected function drupalGet($path, array $options = [], array $headers = []) {
    if (empty($headers['Accept']) && empty($headers['accept'])) {
      $headers['Accept'] = 'application/vnd.api+json';
    }
    return parent::drupalGet($path, $options, $headers);
  }

  /**
   * Test the GET method.
   */
  public function testRead() {
    $num_articles = 61;
    $this->createDefaultContent($num_articles, 5, TRUE, TRUE, static::IS_NOT_MULTILINGUAL);
    // Make the link for node/3 to point to an entity.
    $this->nodes[3]->field_link->setValue(['uri' => 'entity:node/' . $this->nodes[2]->id()]);
    $this->nodes[3]->save();
    $this->nodes[40]->uid->set(0, 1);
    $this->nodes[40]->save();

    // 1. Make sure the api root is under '/api' and not '/jsonapi'.
    /** @var \Symfony\Component\Routing\RouteCollection $route_collection */
    $route_collection = \Drupal::service('router.route_provider')
      ->getRoutesByPattern('/api');
    $this->assertInstanceOf(
      Route::class, $route_collection->get('jsonapi.resource_list')
    );
    $this->drupalGet('/jsonapi');
    $this->assertSession()->statusCodeEquals(404);

    // 2. Make sure the count is included in collections. This also tests the
    // overridden paths.
    $output = Json::decode($this->drupalGet('/api/articles'));
    $this->assertSame($num_articles, (int) $output['meta']['count']);
    $this->assertSession()->statusCodeEquals(200);

    // 3. Check disabled resources.
    $this->drupalGet('/api/taxonomy_vocabulary/taxonomy_vocabulary');
    $this->assertSession()->statusCodeEquals(404);

    // 4. Check renamed fields.
    $output = Json::decode($this->drupalGet('/api/articles/' . $this->nodes[0]->uuid()));
    $this->assertArrayNotHasKey('type', $output['data']['attributes']);
    $this->assertArrayHasKey('contentType', $output['data']['relationships']);
    $this->assertSame('contentTypes', $output['data']['relationships']['contentType']['data']['type']);
    $output = Json::decode($this->drupalGet('/api/contentTypes/' . $this->nodes[0]->type->entity->uuid()));
    $this->assertArrayNotHasKey('type', $output['data']['attributes']);
    $this->assertSame('article', $output['data']['attributes']['machineName']);

    // 5. Check disabled fields.
    $output = Json::decode($this->drupalGet('/api/articles/' . $this->nodes[1]->uuid()));
    $this->assertArrayNotHasKey('uuid', $output['data']['attributes']);

    // 6. Test the field enhancers: DateTimeEnhancer.
    $output = Json::decode($this->drupalGet('/api/articles/' . $this->nodes[2]->uuid()));
    $timestamp = \DateTime::createFromFormat('Y-m-d\TH:i:sO', $output['data']['attributes']['createdAt'])
      ->format('U');
    $this->assertSame((int) $timestamp, $this->nodes[2]->getCreatedTime());

    // 7. Test the field enhancers: UuidLinkEnhancer.
    $output = Json::decode($this->drupalGet('/api/articles/' . $this->nodes[3]->uuid()));
    $expected_link = 'entity:node/article/' . $this->nodes[2]->uuid();
    $this->assertSame($expected_link, $output['data']['attributes']['link']['uri']);

    // 8. Test the field enhancers: SingleNestedEnhancer.
    $output = Json::decode($this->drupalGet('/api/articles/' . $this->nodes[3]->uuid()));
    $this->assertInternalType('string', $output['data']['attributes']['body']);

    // 9. Test the related endpoint.
    // This tests the overridden resource name, the overridden field names and
    // the disabled fields.
    $output = Json::decode($this->drupalGet('/api/articles/' . $this->nodes[4]->uuid() . '/contentType'));
    $this->assertArrayNotHasKey('type', $output['data']['attributes']);
    $this->assertSame('article', $output['data']['attributes']['machineName']);
    $this->assertSame('contentTypes', $output['data']['type']);
    $this->assertArrayNotHasKey('uuid', $output['data']['attributes']);

    // 10. Test the relationships endpoint.
    $output = Json::decode($this->drupalGet('/api/articles/' . $this->nodes[4]->uuid() . '/relationships/contentType'));
    $this->assertSame('contentTypes', $output['data']['type']);
    $this->assertArrayHasKey('id', $output['data']);

    // 11. Test the related endpoint on a multiple cardinality relationship.
    $output = Json::decode($this->drupalGet('/api/articles/' . $this->nodes[5]->uuid() . '/tags'));
    $this->assertCount(count($this->nodes[5]->get('field_tags')->getValue()), $output['data']);
    $this->assertSame('taxonomy_term--tags', $output['data'][0]['type']);

    // 12. Test the relationships endpoint.
    $output = Json::decode($this->drupalGet('/api/articles/' . $this->nodes[5]->uuid() . '/relationships/tags'));
    $this->assertCount(count($this->nodes[5]->get('field_tags')->getValue()), $output['data']);
    $this->assertArrayHasKey('id', $output['data'][0]);

    // 13. Test a disabled related resource of single cardinality.
    $this->drupalGet('/api/taxonomy_term/tags/' . $this->tags[0]->uuid() . '/vid');
    $this->assertSession()->statusCodeEquals(404);
    $this->drupalGet('/api/taxonomy_term/tags/' . $this->tags[0]->uuid() . '/relationships/vid');
    $this->assertSession()->statusCodeEquals(404);

    // 14. Test a disabled related resource of multiple cardinality.
    $this->tags[1]->vocabs->set(0, 'tags');
    $this->tags[1]->save();
    $output = Json::decode($this->drupalGet('/api/taxonomy_term/tags/' . $this->tags[0]->uuid() . '/vocabs'));
    $this->assertTrue(empty($output['data']));
    $output = Json::decode($this->drupalGet('/api/taxonomy_term/tags/' . $this->tags[0]->uuid() . '/relationships/vocabs'));
    $this->assertTrue(empty($output['data']));

    // 15. Test included resource.
    $output = Json::decode($this->drupalGet(
      '/api/articles/' . $this->nodes[6]->uuid(),
      ['query' => ['include' => 'owner']]
    ));
    $this->assertSame('user--user', $output['included'][0]['type']);

    // 16. Test disabled included resources.
    $output = Json::decode($this->drupalGet(
      '/api/taxonomy_term/tags/' . $this->tags[0]->uuid(),
      ['query' => ['include' => 'vocabs,vid']]
    ));
    $this->assertArrayNotHasKey('included', $output);

    // 17. Test nested filters with renamed field.
    $output = Json::decode($this->drupalGet(
      '/api/articles',
      [
        'query' => [
          'filter' => [
            'owner.name' => [
              'value' => User::load(1)->getAccountName(),
            ],
          ],
        ],
      ]
    ));
    // There is only one article for the admin.
    $this->assertSame($this->nodes[40]->uuid(), $output['data'][0]['id']);
  }

  /**
   * Test POST/PATCH.
   */
  public function testWrite() {
    $this->createDefaultContent(0, 3, FALSE, FALSE, static::IS_NOT_MULTILINGUAL);
    // 1. Successful post.
    $collection_url = Url::fromRoute('jsonapi.articles.collection');
    $body = [
      'data' => [
        'type' => 'articles',
        'attributes' => [
          'langcode' => 'en',
          'title' => 'My custom title',
          'default_langcode' => '1',
          'body' => 'Custom value',
          'timestamp' => '2017-12-23T08:45:17+0100',
        ],
        'relationships' => [
          'contentType' => [
            'data' => [
              'type' => 'contentTypes',
              'id' => NodeType::load('article')->uuid(),
            ],
          ],
          'owner' => [
            'data' => ['type' => 'user--user', 'id' => User::load(1)->uuid()],
          ],
          'tags' => [
            'data' => [
              ['type' => 'taxonomy_term--tags', 'id' => $this->tags[0]->uuid()],
              ['type' => 'taxonomy_term--tags', 'id' => $this->tags[1]->uuid()],
            ],
          ],
        ],
      ],
    ];
    $response = $this->request('POST', $collection_url, [
      'body' => Json::encode($body),
      'auth' => [$this->user->getUsername(), $this->user->pass_raw],
      'headers' => ['Content-Type' => 'application/vnd.api+json'],
    ]);
    $created_response = Json::decode((string) $response->getBody());
    $this->assertEquals(201, $response->getStatusCode());
    $this->assertArrayHasKey('internalId', $created_response['data']['attributes']);
    $this->assertCount(2, $created_response['data']['relationships']['tags']['data']);
    $this->assertSame($created_response['data']['links']['self'], $response->getHeader('Location')[0]);
    $date = new \DateTime($body['data']['attributes']['timestamp']);
    $created_node = Node::load($created_response['data']['attributes']['internalId']);
    $this->assertSame((int) $date->format('U'), (int) $created_node->get('field_timestamp')->value);

    // 2. Successful relationships PATCH.
    $uuid = $created_response['data']['id'];
    $relationships_url = Url::fromUserInput('/api/articles/' . $uuid . '/relationships/tags');
    $body = [
      'data' => [
        ['type' => 'taxonomy_term--tags', 'id' => $this->tags[2]->uuid()]
      ],
    ];
    $response = $this->request('POST', $relationships_url, [
      'body' => Json::encode($body),
      'auth' => [$this->user->getUsername(), $this->user->pass_raw],
      'headers' => ['Content-Type' => 'application/vnd.api+json'],
    ]);
    $created_response = Json::decode((string) $response->getBody());
    $this->assertCount(3, $created_response['data']);
  }

  /**
   * Creates the JSON API Resource Config entities to override the resources.
   */
  protected static function overrideResources() {
    // Disable the user resource.
    JsonapiResourceConfig::create([
      'id' => 'taxonomy_vocabulary--taxonomy_vocabulary',
      'disabled' => TRUE,
      'path' => 'taxonomy_vocabulary/taxonomy_vocabulary',
      'resourceType' => 'taxonomy_vocabulary--taxonomy_vocabulary',
    ])->save();
    // Override paths and fields in the articles resource.
    JsonapiResourceConfig::create([
      'id' => 'node--article',
      'disabled' => FALSE,
      'path' => 'articles',
      'resourceType' => 'articles',
      'resourceFields' => [
        'nid' => [
          'fieldName' => 'nid',
          'publicName' => 'internalId',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'uuid' => [
          'fieldName' => 'uuid',
          'publicName' => 'uuid',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'vid' => [
          'fieldName' => 'vid',
          'publicName' => 'vid',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'langcode' => [
          'fieldName' => 'langcode',
          'publicName' => 'langcode',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'type' => [
          'fieldName' => 'type',
          'publicName' => 'contentType',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'status' => [
          'fieldName' => 'status',
          'publicName' => 'isPublished',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'title' => [
          'fieldName' => 'title',
          'publicName' => 'title',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'uid' => [
          'fieldName' => 'uid',
          'publicName' => 'owner',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'created' => [
          'fieldName' => 'created',
          'publicName' => 'createdAt',
          'enhancer' => [
            'id' => 'date_time',
            'settings' => ['dateTimeFormat' => 'Y-m-d\TH:i:sO'],
          ],
          'disabled' => FALSE,
        ],
        'changed' => [
          'fieldName' => 'changed',
          'publicName' => 'updatedAt',
          'enhancer' => [
            'id' => 'date_time',
            'settings' => ['dateTimeFormat' => 'Y-m-d\TH:i:sO'],
          ],
          'disabled' => FALSE,
        ],
        'promote' => [
          'fieldName' => 'promote',
          'publicName' => 'isPromoted',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'sticky' => [
          'fieldName' => 'sticky',
          'publicName' => 'sticky',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'revision_timestamp' => [
          'fieldName' => 'revision_timestamp',
          'publicName' => 'revision_timestamp',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'revision_uid' => [
          'fieldName' => 'revision_uid',
          'publicName' => 'revision_uid',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'revision_log' => [
          'fieldName' => 'revision_log',
          'publicName' => 'revision_log',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'revision_translation_affected' => [
          'fieldName' => 'revision_translation_affected',
          'publicName' => 'revision_translation_affected',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'default_langcode' => [
          'fieldName' => 'default_langcode',
          'publicName' => 'default_langcode',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'path' => [
          'fieldName' => 'path',
          'publicName' => 'path',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'body' => [
          'fieldName' => 'body',
          'publicName' => 'body',
          'enhancer' => ['id' => 'nested', 'settings' => ['path' => 'value']],
          'disabled' => FALSE,
        ],
        'field_link' => [
          'fieldName' => 'field_link',
          'publicName' => 'link',
          'enhancer' => ['id' => 'uuid_link'],
          'disabled' => FALSE,
        ],
        'field_timestamp' => [
          'fieldName' => 'field_timestamp',
          'publicName' => 'timestamp',
          'enhancer' => [
            'id' => 'date_time',
            'settings' => ['dateTimeFormat' => 'Y-m-d\TH:i:sO'],
          ],
          'disabled' => FALSE,
        ],
        'comment' => [
          'fieldName' => 'comment',
          'publicName' => 'comment',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'field_image' => [
          'fieldName' => 'field_image',
          'publicName' => 'image',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'field_recipes' => [
          'fieldName' => 'field_recipes',
          'publicName' => 'recipes',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'field_tags' => [
          'fieldName' => 'field_tags',
          'publicName' => 'tags',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
      ],
    ])->save();
    // Override the resource type in the node_type resource.
    JsonapiResourceConfig::create([
      'id' => 'node_type--node_type',
      'disabled' => FALSE,
      'path' => 'contentTypes',
      'resourceType' => 'contentTypes',
      'resourceFields' => [
        'type' => [
          'fieldName' => 'type',
          'publicName' => 'machineName',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'status' => [
          'fieldName' => 'status',
          'publicName' => 'isEnabled',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'langcode' => [
          'fieldName' => 'langcode',
          'publicName' => 'langcode',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'uuid' => [
          'fieldName' => 'uuid',
          'publicName' => 'uuid',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
      ],
    ])->save();
  }

}
