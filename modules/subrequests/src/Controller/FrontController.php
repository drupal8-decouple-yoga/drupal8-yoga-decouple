<?php

namespace Drupal\subrequests\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\subrequests\Blueprint\BlueprintManager;
use Drupal\subrequests\Blueprint\Parser;
use Drupal\subrequests\Blueprint\RequestTree;
use Drupal\subrequests\SubrequestsManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Front controller to process Subrequests requests.
 */
class FrontController extends ControllerBase {

  /**
   * @var \Drupal\subrequests\Blueprint\BlueprintManager
   */
  protected $blueprintManager;

  /**
   * @var \Drupal\subrequests\SubrequestsManager
   */
  protected $subrequestsManager;

  /**
   * FrontController constructor.
   */
  public function __construct(BlueprintManager $blueprint_manager, SubrequestsManager $subrequests_manager) {
    $this->blueprintManager = $blueprint_manager;
    $this->subrequestsManager = $subrequests_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('subrequests.blueprint_manager'),
      $container->get('subrequests.subrequests_manager')
    );
  }

  /**
   * Controller handler.
   */
  public function handle(Request $request) {
    $data = '';
    if ($request->getMethod() === Request::METHOD_POST) {
      $data = $request->getContent();
    }
    elseif ($request->getMethod() === Request::METHOD_GET) {
      $data = $request->query->get('query', '');
    }
    $tree = $this->blueprintManager->parse($data, $request);
    $responses = $this->subrequestsManager->request($tree);
    $master_request = $tree->getMasterRequest();
    $output_format = $master_request->getRequestFormat();
    if ($output_format === 'html') {
      // Change the default format from html to multipart-related.
      $output_format = 'multipart-related';
    }
    $master_request->getMimeType($output_format);
    return $this->blueprintManager->combineResponses($responses, $output_format);
  }

}
