<?php

namespace Drupal\Tests\feeds\Unit\Entity;

use Drupal\feeds\Entity\FeedType;
use Drupal\feeds\Plugin\Type\FeedsPluginManager;
use Drupal\feeds\Plugin\Type\Parser\ParserInterface;
use Drupal\Tests\feeds\Unit\FeedsUnitTestCase;

/**
 * @coversDefaultClass \Drupal\feeds\Entity\FeedType
 * @group feeds
 */
class FeedTypeTest extends FeedsUnitTestCase {

  /**
   * The feed type under test.
   *
   * @var \Drupal\feeds\Entity\FeedType
   */
  protected $feedType;

  /**
   * Gets a feed type for testing.
   *
   * @param string $feed_type_id
   *   The feed type ID.
   * @param array $stubs
   *   (optional) The methods to mock.
   *
   * @return \Drupal\feeds\FeedTypeInterface
   *   The mocked feed type.
   */
  protected function getFeedTypeMock($feed_type_id, array $stubs = []) {
    // Plugin manager.
    $pluginManager = $this->getMockBuilder(FeedsPluginManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $pluginManager->expects($this->any())
      ->method('getDefinitions')
      ->will($this->returnValue([]));

    $default_stubs = [
      'getParser',
      'getSourcePluginManager',
      'alter',
    ];

    $feed_type = $this->getMockBuilder(FeedType::class)
      ->setConstructorArgs([
        [
          'id' => $feed_type_id,
          'label' => 'My Feed',
          'custom_sources' => [
            'source1' => [
              'label' => 'Source 1',
              'value' => 'Source 1',
            ],
          ],
        ],
        'feeds_feed_type',
      ])
      ->setMethods(array_merge($default_stubs, $stubs))
      ->getMock();

    // Parser.
    $parser = $this->getMockBuilder(ParserInterface::class)
      ->getMock();
    $parser->expects($this->any())
      ->method('getMappingSources')
      ->will($this->returnValue([]));
    $feed_type->expects($this->any())
      ->method('getParser')
      ->will($this->returnValue($parser));

    // Source plugin manager.
    $feed_type->expects($this->any())
      ->method('getSourcePluginManager')
      ->will($this->returnValue($pluginManager));

    return $feed_type;
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->feedType = $this->getFeedTypeMock($this->randomMachineName());
  }

  /**
   * @covers ::addCustomSource
   */
  public function testAddCustomSource() {
    // Add a custom source.
    $this->assertSame($this->feedType, $this->feedType->addCustomSource('source2', [
      'label' => 'Source 2',
      'value' => 'Source 2',
    ]));

    // Assert that the source exists as one of the mapping sources.
    $expected = [
      'source1' => [
        'label' => 'Source 1',
        'value' => 'Source 1',
      ],
      'source2' => [
        'label' => 'Source 2',
        'value' => 'Source 2',
      ],
    ];
    $this->assertSame($expected, $this->feedType->getMappingSources());
  }

  /**
   * @covers ::getCustomSource
   */
  public function testGetCustomSource() {
    // Get an existing source.
    $expected = [
      'label' => 'Source 1',
      'value' => 'Source 1',
    ];
    $this->assertSame($expected, $this->feedType->getCustomSource('source1'));

    // Get a non-existing source.
    $this->assertSame(NULL, $this->feedType->getCustomSource('non_existing'));
  }

  /**
   * @covers ::customSourceExists
   */
  public function testCustomSourceExists() {
    $this->assertSame(TRUE, $this->feedType->customSourceExists('source1'));
    $this->assertSame(FALSE, $this->feedType->customSourceExists('non_existing'));
  }

  /**
   * @covers ::removeCustomSource
   */
  public function testRemoveCustomSource() {
    // Remove source 1.
    $this->assertSame($this->feedType, $this->feedType->removeCustomSource('source1'));
    $this->assertSame([], $this->feedType->getMappingSources());
  }

}
