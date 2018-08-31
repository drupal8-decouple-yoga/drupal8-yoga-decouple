<?php

namespace Drupal\Tests\subrequests\Normalizer;

use Drupal\Component\Serialization\Json;
use Drupal\subrequests\Normalizer\MultiresponseJsonNormalizer;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @coversDefaultClass \Drupal\subrequests\Normalizer\MultiresponseJsonNormalizer
 * @group subrequests
 */
class MultiresponseJsonNormalizerTest extends UnitTestCase {

  /**
   * @var \Drupal\subrequests\Normalizer\MultiresponseJsonNormalizer
   */
  protected $sut;

  protected function setUp() {
    parent::setUp();
    $this->sut = new MultiresponseJsonNormalizer();
  }

  /**
   * @dataProvider dataProviderSupportsNormalization
   * @covers ::supportsNormalization
   */
  public function testSupportsNormalization($data, $format, $is_supported) {
    $actual = $this->sut->supportsNormalization($data, $format);
    $this->assertSame($is_supported, $actual);
  }

  public function dataProviderSupportsNormalization() {
    return [
      [[Response::create('')], 'json', TRUE],
      [[], 'json', TRUE],
      [[Response::create('')], 'fail', FALSE],
      [NULL, 'json', FALSE],
      [[Response::create(''), NULL], 'json', FALSE],
    ];
  }

  /**
   * @covers ::normalize
   */
  public function testNormalize() {
    $sub_content_type = $this->getRandomGenerator()->string();
    $data = [
      Response::create('Foo!', 200, ['Content-ID' => '<f>']),
      Response::create('Bar', 200, ['Content-ID' => '<b>']),
    ];
    $actual = $this->sut->normalize($data, NULL, ['sub-content-type' => $sub_content_type]);
    $this->assertSame(
      'application/json; type=' . $sub_content_type,
      $actual['headers']['Content-Type']
    );
    $parsed = Json::decode($actual['content']);
    $this->assertSame('Foo!', $parsed['f']['body']);
    $this->assertSame('Bar', $parsed['b']['body']);
  }

}
