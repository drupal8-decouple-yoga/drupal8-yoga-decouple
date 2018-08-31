<?php

namespace Drupal\subrequests;

use Symfony\Component\HttpFoundation\Request;

/**
 * Value class that holds the execution tree.
 */
class SubrequestsTree extends \ArrayObject {

  /**
   * The master request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $masterRequest;

  /**
   * Adds a sequence of subrequests to the stack.
   *
   * @param \Drupal\subrequests\Subrequest[] $subrequests
   */
  public function stack($subrequests) {
    // Make sure we only push Subrequest objects.
    $this->append(array_filter($subrequests, function ($subrequest) {
      return $subrequest instanceof Subrequest;
    }));
  }

  /**
   * Gets the number of levels in the stack.
   *
   * @return int
   */
  public function getNumLevels() {
    return $this->count();
  }

  /**
   * Gets the lowest level.
   *
   * @return \Drupal\subrequests\Subrequest[]
   *   The subrequests in the level.
   */
  public function getLowestLevel() {
    return $this->offsetGet($this->count() - 1);
  }

  /**
   * Gets the master request.
   *
   * @return \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   */
  public function getMasterRequest() {
    return $this->masterRequest;
  }

  /**
   * Sets the master request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   */
  public function setMasterRequest(Request $request) {
    $this->masterRequest = $request;
  }

  /**
   * Gets all the subrequest IDs.
   *
   * @return \Drupal\subrequests\Subrequest[]
   *   All the subrequests in all levels.
   */
  public function allIds() {
    $subrequests = [];
    foreach ($this as $item) {
      $subrequests = array_merge($subrequests, array_values($item));
    }
    $all_request_ids = array_map(function (Subrequest $subrequest) {
      return $subrequest->requestId;
    }, $subrequests);
    array_unshift($all_request_ids, '<ROOT>');
    return array_unique($all_request_ids);
  }

}
