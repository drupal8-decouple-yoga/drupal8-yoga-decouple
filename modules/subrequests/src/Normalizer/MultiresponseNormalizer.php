<?php

namespace Drupal\subrequests\Normalizer;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes multiple response objects into a single string.
 */
class MultiresponseNormalizer implements NormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $delimiter = md5(microtime());

    // Prepare the root content type header.
    $content_type = sprintf(
      'multipart/related; boundary="%s", type=%s',
      $delimiter,
      $context['sub-content-type']
    );
    $headers = ['Content-Type' => $content_type];

    $separator = sprintf("\r\n--%s\r\n", $delimiter);
    // Join the content responses with the separator.
    $content_items = array_map(function (Response $part_response) {
      $part_response->headers->set('Status', $part_response->getStatusCode());
      return sprintf(
        "%s\r\n%s",
        $part_response->headers,
        $part_response->getContent()
      );
    }, (array) $object);
    $content = sprintf("--%s\r\n", $delimiter) . implode($separator, $content_items) . sprintf("\r\n--%s--", $delimiter);
    return [
      'content' => $content,
      'headers' => $headers,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    if ($format !== 'multipart-related') {
      return FALSE;
    }
    if (!is_array($data)) {
      return FALSE;
    }
    $responses = array_filter($data, function ($response) {
      return $response instanceof Response;
    });
    if (count($responses) !== count($data)) {
      return FALSE;
    }
    return TRUE;
  }

}
