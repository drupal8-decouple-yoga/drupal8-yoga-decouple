<?php

namespace Drupal\Tests\subrequests\Normalizer;

use Drupal\Component\Serialization\Json;
use Drupal\subrequests\Normalizer\JsonSubrequestDenormalizer;
use Drupal\subrequests\Subrequest;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @coversDefaultClass \Drupal\subrequests\Normalizer\JsonSubrequestDenormalizer
 * @group subrequests
 */
class JsonSubrequestDenormalizerTest extends UnitTestCase {

  /**
   * @var \Drupal\subrequests\Normalizer\JsonSubrequestDenormalizer
   */
  protected $sut;

  public function setUp() {
    parent::setUp();
    $this->sut = new JsonSubrequestDenormalizer();
  }

  /**
   * @covers ::denormalize
   */
  public function testDenormalize() {
    $class = Request::class;
    $data = new Subrequest([
      'requestId' => 'oof',
      'body' => ['bar' => 'foo'],
      'headers' => ['Authorization' => 'Basic ' . base64_encode('lorem:ipsum')],
      'waitFor' => ['lorem'],
      '_resolved' => FALSE,
      'uri' => 'oop',
      'action' => 'create',
    ]);
    $request = Request::create('');
    $request->setSession(new Session());
    $actual = $this->sut->denormalize($data, $class, NULL, ['master_request' => $request]);
    $this->assertSame('POST', $actual->getMethod());
    $this->assertEquals(['bar' => 'foo'], Json::decode($actual->getContent()));
    $this->assertSame('<oof>', $actual->headers->get('Content-ID'));
    $this->assertSame('lorem', $actual->headers->get('PHP_AUTH_USER'));
    $this->assertSame('ipsum', $actual->headers->get('PHP_AUTH_PW'));
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
    $subrequest = new Subrequest([
      'requestId' => 'oof',
      'body' => ['bar' => 'foo'],
      'headers' => [],
      'waitFor' => ['lorem'],
      '_resolved' => FALSE,
      'uri' => 'oop',
      'action' => 'create',
    ]);
    return [
      [$subrequest, Request::class, NULL, TRUE],
      ['fail', Request::class, NULL, FALSE],
      [$subrequest, 'fail', NULL, FALSE],
    ];
  }

}
