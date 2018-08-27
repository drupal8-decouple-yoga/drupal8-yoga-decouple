<?php

namespace Drupal\Tests\feeds\Unit\Feeds\Target;

use Drupal\feeds\Feeds\Target\Email;

/**
 * @coversDefaultClass \Drupal\feeds\Feeds\Target\Email
 * @group feeds
 */
class EmailTest extends FieldTargetTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getTargetClass() {
    return Email::class;
  }

  /**
   * Basic test for the email target.
   *
   * @covers ::prepareValue
   */
  public function testPrepareValue() {
    $method = $this->getMethod('Drupal\feeds\Feeds\Target\Email', 'prepareTarget')->getClosure();

    $configuration = [
      'feed_type' => $this->getMock('Drupal\feeds\FeedTypeInterface'),
      'target_definition' => $method($this->getMockFieldDefinition()),
    ];
    $target = new Email($configuration, 'email', []);

    $method = $this->getProtectedClosure($target, 'prepareValue');

    $values = ['value' => 'string'];
    $method(0, $values);
    $this->assertSame($values['value'], '');

    $values = ['value' => 'admin@example.com'];
    $method(0, $values);
    $this->assertSame($values['value'], 'admin@example.com');
  }

}
