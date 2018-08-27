<?php

namespace Drupal\Tests\feeds\Unit\Plugin\QueueWorker;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\feeds\Exception\EmptyFeedException;
use Drupal\Tests\feeds\Unit\FeedsUnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @coversDefaultClass \Drupal\feeds\Plugin\QueueWorker\FeedQueueWorkerBase
 * @group feeds
 */
class FeedQueueWorkerBaseTest extends FeedsUnitTestCase {

  /**
   * Tests various methods on the FeedQueueWorkerBase class.
   *
   * @expectedException \RuntimeException
   */
  public function test() {
    $container = new ContainerBuilder();
    $container->set('queue', $this->getMock('Drupal\Core\Queue\QueueFactory', [], [], '', FALSE));
    $container->set('event_dispatcher', new EventDispatcher());
    $container->set('account_switcher', $this->getMockedAccountSwitcher());
    $container->set('entity_type.manager', $this->getMock(EntityTypeManagerInterface::class));

    $plugin = $this->getMockForAbstractClass('Drupal\feeds\Plugin\QueueWorker\FeedQueueWorkerBase', [], '', FALSE);
    $plugin = $plugin::create($container, [], '', []);

    $method = $this->getProtectedClosure($plugin, 'handleException');
    $method($this->getMockFeed(), new EmptyFeedException());
    $method($this->getMockFeed(), new \RuntimeException());
  }

}
