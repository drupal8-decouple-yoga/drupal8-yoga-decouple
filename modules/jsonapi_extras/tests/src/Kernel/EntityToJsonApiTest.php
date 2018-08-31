<?php

namespace Drupal\Tests\jsonapi_extras\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\file\Entity\File;
use Drupal\jsonapi\LinkManager\LinkManager;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\image\Kernel\ImageFieldCreationTrait;
use Drupal\Tests\jsonapi\Kernel\JsonapiKernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\jsonapi_extras\EntityToJsonApi
 * @group jsonapi
 * @group jsonapi_serializer
 * @group legacy
 *
 * @internal
 */
class EntityToJsonApiTest extends JsonapiKernelTestBase {

  use ImageFieldCreationTrait;

  /**
   * System under test.
   *
   * @var \Drupal\jsonapi_extras\EntityToJsonApi
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'jsonapi',
    'jsonapi_extras',
    'field',
    'node',
    'serialization',
    'system',
    'taxonomy',
    'text',
    'user',
    'file',
    'image',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Add the entity schemas.
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('file');
    // Add the additional table schemas.
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);
    $this->installSchema('file', ['file_usage']);
    $this->nodeType = NodeType::create([
      'type' => 'article',
    ]);
    $this->nodeType->save();
    $this->createEntityReferenceField(
      'node',
      'article',
      'field_tags',
      'Tags',
      'taxonomy_term',
      'default',
      ['target_bundles' => ['tags']],
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );

    $this->createImageField('field_image', 'article');

    $this->user = User::create([
      'name' => 'user1',
      'mail' => 'user@localhost',
    ]);
    $this->user2 = User::create([
      'name' => 'user2',
      'mail' => 'user2@localhost',
    ]);

    $this->user->save();
    $this->user2->save();

    $this->vocabulary = Vocabulary::create(['name' => 'Tags', 'vid' => 'tags']);
    $this->vocabulary->save();

    $this->term1 = Term::create([
      'name' => 'term1',
      'vid' => $this->vocabulary->id(),
    ]);
    $this->term2 = Term::create([
      'name' => 'term2',
      'vid' => $this->vocabulary->id(),
    ]);

    $this->term1->save();
    $this->term2->save();

    $this->file = File::create([
      'uri' => 'public://example.png',
      'filename' => 'example.png',
    ]);
    $this->file->save();

    $this->node = Node::create([
      'title' => 'dummy_title',
      'type' => 'article',
      'uid' => 1,
      'field_tags' => [
        ['target_id' => $this->term1->id()],
        ['target_id' => $this->term2->id()],
      ],
      'field_image' => [
        [
          'target_id' => $this->file->id(),
          'alt' => 'test alt',
          'title' => 'test title',
          'width' => 10,
          'height' => 11,
        ],
      ],
    ]);

    $this->node->save();

    $link_manager = $this->prophesize(LinkManager::class);
    $link_manager
      ->getEntityLink(
        Argument::any(),
        Argument::any(),
        Argument::type('array'),
        Argument::type('string')
      )
      ->willReturn('dummy_entity_link');
    $link_manager
      ->getRequestLink(Argument::any())
      ->willReturn('dummy_document_link');
    $this->container->set('jsonapi.link_manager', $link_manager->reveal());

    $this->nodeType = NodeType::load('article');

    $this->role = Role::create([
      'id' => RoleInterface::ANONYMOUS_ID,
      'permissions' => [
        'access content',
      ],
    ]);
    $this->role->save();
    $this->sut = $this->container->get('jsonapi_extras.entity.to_jsonapi');
  }

  /**
   * @covers ::serialize
   * @covers ::normalize
   */
  public function testSerialize() {
    $entities = [
      [
        $this->node,
        ['field_tags'],
        [
          [
            'type' => 'taxonomy_term--tags',
            'attributes' => [
              'tid' => (int) $this->term1->id(),
              'uuid' => $this->term1->uuid(),
              'name' => $this->term1->label(),
            ],
          ],
          [
            'type' => 'taxonomy_term--tags',
            'attributes' => [
              'tid' => (int) $this->term2->id(),
              'uuid' => $this->term2->uuid(),
              'name' => $this->term2->label(),
            ],
          ],
        ],
      ],
      [$this->user, [], []],
      [$this->file, [], []],
      [$this->term1, [], []],
      // Make sure we also support configuration entities.
      [$this->vocabulary, [], []],
      [$this->nodeType, [], []],
      [$this->role, [], []],
    ];

    array_walk(
      $entities,
      function ($data) {
        list($entity, $include_fields, $expected_includes) = $data;
        $this->assertEntity($entity, $include_fields, $expected_includes);
      }
    );
  }

  /**
   * Checks entity's serialization/normalization.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to serialize/normalize.
   * @param string[] $include_fields
   *   The list of fields to include.
   * @param array[] $expected_includes
   *   The list of partial structures of the "included" key.
   */
  protected function assertEntity(
    EntityInterface $entity,
    array $include_fields = [],
    array $expected_includes = []
  ) {
    $output = $this->sut->serialize($entity, $include_fields);

    static::assertInternalType('string', $output);
    $this->assertJsonApi(Json::decode($output));

    $output = $this->sut->normalize($entity, $include_fields);

    static::assertInternalType('array', $output);
    $this->assertJsonApi($output);

    // Check the includes if they were passed.
    if (!empty($include_fields)) {
      $this->assertJsonApiIncludes($output, $expected_includes);
    }
  }

  /**
   * Helper to assert if a string is valid JSON API.
   *
   * @param array $structured
   *   The JSON API data to check.
   */
  protected function assertJsonApi(array $structured) {
    static::assertNotEmpty($structured['data']['type']);
    static::assertNotEmpty($structured['data']['id']);
    static::assertNotEmpty($structured['data']['attributes']);
    static::assertInternalType('string', $structured['links']['self']);
  }

  /**
   * Shallowly checks the list of includes.
   *
   * @param array $structured
   *   The JSON API data to check.
   * @param array[] $includes
   *   The list of partial structures of the "included" key.
   */
  protected function assertJsonApiIncludes(array $structured, array $includes) {
    static::assertFalse(
      empty($structured['included']),
      'The list of includes should is empty.'
    );

    foreach ($includes as $i => $include) {
      static::assertFalse(
        empty($structured['included'][$i]),
        sprintf('The include #%d does not exist.', $i)
      );
      static::assertSame(
        $include['type'],
        $structured['included'][$i]['type'],
        sprintf('The type of include #%d does not match expected value.', $i)
      );

      foreach ($include['attributes'] as $attribute => $expected_value) {
        static::assertSame(
          $expected_value,
          $structured['included'][$i]['attributes'][$attribute],
          sprintf(
            'The "%s" of include #%d doest match the expected value.',
            $attribute,
            $i
          )
        );
      }
    }
  }

}
