<?php

namespace Drupal\Tests\subrequests\Unit;

use Drupal\subrequests\JsonPathReplacer;
use Drupal\subrequests\Subrequest;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @coversDefaultClass \Drupal\subrequests\JsonPathReplacer
 * @group subrequests
 */
class JsonPathReplacerTest extends UnitTestCase {

  /**
   * @var \Drupal\subrequests\JsonPathReplacer
   */
  protected $sut;

  protected function setUp() {
    parent::setUp();
    $this->sut = new JsonPathReplacer();
  }

  /**
   * @covers ::replaceBatch
   */
  public function testReplaceBatch() {
    $batch = $responses = [];
    $batch[] = new Subrequest([
      'uri' => '/ipsum/{{foo.body@$.things[*]}}/{{bar.body@$.things[*]}}/{{foo.body@$.stuff}}',
      'action' => 'sing',
      'requestId' => 'oop',
      'headers' => [],
      '_resolved' => FALSE,
      'body' => ['answer' => '{{foo.body@$.stuff}}'],
      'waitFor' => ['foo'],
    ]);
    $batch[] = new Subrequest([
      'uri' => '/dolor/{{foo.body@$.stuff}}',
      'action' => 'create',
      'requestId' => 'oof',
      'headers' => [],
      '_resolved' => FALSE,
      'body' => 'bar',
      'waitFor' => ['foo'],
    ]);
    $response = Response::create('{"things":["what","keep","talking"],"stuff":42}');
    $response->headers->set('Content-ID', '<foo>');
    $responses[] = $response;
    $response = Response::create('{"things":["the","plane","is"],"stuff":"delayed"}');
    $response->headers->set('Content-ID', '<bar>');
    $responses[] = $response;
    $actual = $this->sut->replaceBatch($batch, $responses);
    $this->assertCount(10, $actual);
    $paths = array_map(function (Subrequest $subrequest) {
      return [$subrequest->uri, $subrequest->body];
    }, $actual);
    $expected_paths = [
      ['/ipsum/what/the/42', ['answer' => '42']],
      ['/ipsum/what/plane/42', ['answer' => '42']],
      ['/ipsum/what/is/42', ['answer' => '42']],
      ['/ipsum/keep/the/42', ['answer' => '42']],
      ['/ipsum/keep/plane/42', ['answer' => '42']],
      ['/ipsum/keep/is/42', ['answer' => '42']],
      ['/ipsum/talking/the/42', ['answer' => '42']],
      ['/ipsum/talking/plane/42', ['answer' => '42']],
      ['/ipsum/talking/is/42', ['answer' => '42']],
      ['/dolor/42', 'bar'],
    ];
    $this->assertEquals($expected_paths, $paths);
    $this->assertEquals(['answer' => 42], $actual[0]->body);
  }

  /**
   * @covers ::replaceBatch
   */
  public function testReplaceBatchSplit() {
    $batch = $responses = [];
    $batch[] = new Subrequest([
      'uri' => 'test://{{foo.body@$.things[*].id}}/{{foo.body@$.things[*].id}}',
      'action' => 'sing',
      'requestId' => 'oop',
      'headers' => [],
      '_resolved' => FALSE,
      'body' => ['answer' => '{{foo.body@$.stuff}}'],
      'waitFor' => ['foo'],
    ]);
    $response = Response::create('{"things":[{"id":"what"},{"id":"keep"},{"id":"talking"}],"stuff":42}');
    $response->headers->set('Content-ID', '<foo#0>');
    $responses[] = $response;
    $response = Response::create('{"things":[{"id":"the"},{"id":"plane"}],"stuff":"delayed"}');
    $response->headers->set('Content-ID', '<foo#1>');
    $responses[] = $response;
    $actual = $this->sut->replaceBatch($batch, $responses);
    $this->assertCount(10, $actual);
    $paths = array_map(function (Subrequest $subrequest) {
      return [$subrequest->uri, $subrequest->body];
    }, $actual);
    $expected_paths = [
      ['test://what/what', ['answer' => '42']],
      ['test://what/what', ['answer' => 'delayed']],
      ['test://keep/keep', ['answer' => '42']],
      ['test://keep/keep', ['answer' => 'delayed']],
      ['test://talking/talking', ['answer' => '42']],
      ['test://talking/talking', ['answer' => 'delayed']],
      ['test://the/the', ['answer' => '42']],
      ['test://the/the', ['answer' => 'delayed']],
      ['test://plane/plane', ['answer' => '42']],
      ['test://plane/plane', ['answer' => 'delayed']],
    ];
    $this->assertEquals($expected_paths, $paths);
  }

}
