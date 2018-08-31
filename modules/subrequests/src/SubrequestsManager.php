<?php

namespace Drupal\subrequests;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class SubrequestsManager {

  /**
   * The kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The serializer.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $serializer;

  /**
   * The path replacer.
   *
   * @var \Drupal\subrequests\JsonPathReplacer
   */
  protected $replacer;

  public function __construct(HttpKernelInterface $http_kernel, DenormalizerInterface $serializer, JsonPathReplacer $replacer) {
    $this->httpKernel = $http_kernel;
    $this->serializer = $serializer;
    $this->replacer = $replacer;
  }

  public function request(SubrequestsTree $tree) {
    // Loop through all sequential requests and merge them.
    return $this->processBatchesSequence($tree);
  }

  /**
   * Processes all the Subrequests until produce a collection of responses.
   *
   * @param \Drupal\subrequests\SubrequestsTree $tree
   *   The request tree that contains the requesting structure.
   * @param int $_sequence
   *   (internal) The current index in the sequential chain.
   * @param \Symfony\Component\HttpFoundation\Response[] $_responses
   *   (internal) The list of responses accumulated so far.
   *
   * @return \Symfony\Component\HttpFoundation\Response[]
   *   An array of responses when everything has been resolved.
   */
   protected function processBatchesSequence($tree, $_sequence = 0, array $_responses = []) {
     $batch = $tree[$_sequence];
     // Perform all the necessary replacements for the elements in the batch.
     $batch = $this->replacer->replaceBatch($batch, $_responses);
     $results = array_map(function (Subrequest $subrequest) use ($tree) {
       $master_request = $tree->getMasterRequest();
       // Create a Symfony Request object based on the Subrequest.
       /** @var \Symfony\Component\HttpFoundation\Request $request */
       $request = $this->serializer->denormalize(
         $subrequest,
         Request::class,
         NULL,
         ['master_request' => $master_request]
       );
       $response = $this->httpKernel
         ->handle($request, HttpKernelInterface::MASTER_REQUEST);
       // Set the Content-ID header in the response.
       $content_id = sprintf('<%s>', $subrequest->requestId);
       $response->headers->set('Content-ID', $content_id);
       return $response;
     }, $batch);
     // Accumulate the responses for the current batch.
     $_responses = array_merge($_responses, $results);

     // If we're not done, then call recursively with the updated arguments.
     $_sequence++;
     return $_sequence === $tree->count()
       ? $_responses
       : $this->processBatchesSequence($tree, $_sequence, $_responses);
  }

}
