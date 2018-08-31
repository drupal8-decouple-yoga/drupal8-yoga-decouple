<?php

namespace Drupal\Tests\subrequests\Normalizer;

use Drupal\subrequests\Normalizer\JsonBlueprintDenormalizer;
use Drupal\subrequests\Subrequest;
use Drupal\subrequests\SubrequestsTree;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\subrequests\Normalizer\JsonBlueprintDenormalizer
 * @group subrequests
 */
class JsonBlueprintDenormalizerTest extends UnitTestCase {

  /**
   * @var \Drupal\subrequests\Normalizer\JsonBlueprintDenormalizer
   */
  protected $sut;

  protected function setUp() {
    parent::setUp();
    $logger = $this->prophesize(LoggerInterface::class);
    $this->sut = new JsonBlueprintDenormalizer($logger->reveal());
  }

  /**
   * @dataProvider dataProviderSupportsNormalization
   * @covers ::supportsDenormalization
   */
  public function testSupportsDenormalization($data, $type, $format, $is_supported) {
    $actual = $this->sut->supportsDenormalization($data, $type, $format);
    $this->assertSame($is_supported, $actual);
  }

  public function dataProviderSupportsNormalization() {
    return [
      [['a', 'b'], SubrequestsTree::class, 'json', TRUE],
      ['fail', SubrequestsTree::class, 'json', FALSE],
      [['a', 'b'], SubrequestsTree::class, 'fail', FALSE],
      [['fail' => 'a', 'b'], SubrequestsTree::class, 'json', FALSE],
    ];
  }

  public function testDenormalize() {
    $subrequests[] = [
      'uri' => 'lorem',
      'action' => 'view',
      'requestId' => 'foo',
      'body' => '"bar"',
      'headers' => [],
    ];
    $subrequests[] = [
      'uri' => 'ipsum',
      'action' => 'sing',
      'requestId' => 'oop',
      'body' => '[]',
      'waitFor' => ['foo'],
    ];
    $subrequests[] = [
      'uri' => 'lorem%3F%7B%7Bipsum%7D%7D', // lorem?{{ipsum}}
      'action' => 'create',
      'requestId' => 'oof',
      'body' => '"bar"',
      'waitFor' => ['foo'],
    ];
    $actual = $this->sut->denormalize($subrequests, SubrequestsTree::class, 'json', []);
    $tree = new SubrequestsTree();
    $tree->stack([new Subrequest(['waitFor' => ['<ROOT>'], '_resolved' => FALSE, 'body' => 'bar'] + $subrequests[0])]);
    $tree->stack([
      new Subrequest(['headers' => [], '_resolved' => FALSE, 'body' => []] + $subrequests[1]),
      // Make sure the URL is decoded so we can perform apply regular
      // expressions to it.
      new Subrequest(
        [
          'headers' => [],
          '_resolved' => FALSE,
          'body' => 'bar',
          'uri' => 'lorem?{{ipsum}}'
        ] + $subrequests[2]
      )
    ]);
    $this->assertEquals($tree, $actual);
  }

}
