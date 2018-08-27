<?php

namespace Drupal\Tests\feeds\Unit\Feeds\Fetcher;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\feeds\Unit\FeedsUnitTestCase;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\FeedTypeInterface;
use Drupal\feeds\Feeds\Fetcher\HttpFetcher;
use Drupal\feeds\State;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\feeds\Feeds\Fetcher\HttpFetcher
 * @group feeds
 */
class HttpFetcherTest extends FeedsUnitTestCase {

  /**
   * The feed entity.
   *
   * @var \Drupal\feeds\FeedInterface
   */
  protected $feed;

  /**
   * The Feeds fetcher plugin under test.
   *
   * @var \Drupal\feeds\Feeds\Fetcher\HttpFetcher
   */
  protected $fetcher;

  /**
   * A mocked HTTP handler to use within the handler stack.
   *
   * @var \GuzzleHttp\Handler\MockHandler
   */
  protected $mockHandler;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $feed_type = $this->getMock(FeedTypeInterface::class);

    $this->mockHandler = new MockHandler();
    $client = new Client(['handler' => HandlerStack::create($this->mockHandler)]);
    $cache = $this->getMock(CacheBackendInterface::class);

    $file_system = $this->prophesize(FileSystemInterface::class);
    $file_system->tempnam(Argument::type('string'), Argument::type('string'))->will(function ($args) {
      return tempnam($args[0], $args[1]);
    });
    $file_system->realpath(Argument::type('string'))->will(function ($args) {
      return realpath($args[0]);
    });

    $this->fetcher = new HttpFetcher(['feed_type' => $feed_type], 'http', [], $client, $cache, $file_system->reveal());
    $this->fetcher->setStringTranslation($this->getStringTranslationStub());

    $this->feed = $this->prophesize(FeedInterface::class);
    $this->feed->id()->willReturn(1);
    $this->feed->getSource()->willReturn('http://example.com');
  }

  /**
   * Tests a successful fetch from a HTTP source.
   *
   * @covers ::fetch
   */
  public function testFetch() {
    $this->mockHandler->append(new Response(200, [], 'test data'));

    $result = $this->fetcher->fetch($this->feed->reveal(), new State());
    $this->assertSame('test data', $result->getRaw());
  }

  /**
   * Tests fetching from a HTTP source that returns a 304 (not modified).
   *
   * @covers ::fetch
   * @expectedException \Drupal\feeds\Exception\EmptyFeedException
   */
  public function testFetch304() {
    $this->mockHandler->append(new Response(304));
    $this->fetcher->fetch($this->feed->reveal(), new State());
  }

  /**
   * Tests fetching from a HTTP source that returns a 404 (not found).
   *
   * @covers ::fetch
   * @expectedException \RuntimeException
   */
  public function testFetch404() {
    $this->mockHandler->append(new Response(404));
    $this->fetcher->fetch($this->feed->reveal(), new State());
  }

  /**
   * Tests a fetch that fails.
   *
   * @covers ::fetch
   * @expectedException \RuntimeException
   */
  public function testFetchError() {
    $this->mockHandler->append(new RequestException('', new Request(200, 'http://google.com')));
    $this->fetcher->fetch($this->feed->reveal(), new State());
  }

  /**
   * @covers ::onFeedDeleteMultiple
   */
  public function testOnFeedDeleteMultiple() {
    $feed = $this->getMock(FeedInterface::class);
    $feed->expects($this->exactly(3))
      ->method('getSource')
      ->will($this->returnValue('http://example.com'));
    $feeds = [$feed, $feed, $feed];

    $this->fetcher->onFeedDeleteMultiple($feeds, new State());
  }

}
