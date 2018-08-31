<?php

namespace Drupal\subrequests\Normalizer;

use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes multiple response objects into a single string.
 */
class MultiresponseJsonNormalizer implements NormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    // Prepare the root content type header.
    $content_type = sprintf(
      'application/json; type=%s',
      $context['sub-content-type']
    );
    $headers = ['Content-Type' => $content_type];

    // Join the content responses as a JSON object with the separator.
    $output = array_reduce((array) $object, function ($carry, Response $part_response) {
      $part_response->headers->set('Status', $part_response->getStatusCode());
      $content_id = $part_response->headers->get('Content-ID');
      $content_id = substr($content_id, 1, strlen($content_id) - 2);
      $carry[$content_id] = [
        'headers' => $part_response->headers->all(),
        'body' => $part_response->getContent(),
      ];
      return $carry;
    }, []);
    $content = Json::encode($output);
    return [
      'content' => $content,
      'headers' => $headers,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    if ($format !== 'json') {
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
