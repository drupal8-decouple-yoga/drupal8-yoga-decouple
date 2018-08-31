<?php

namespace Drupal\jsonapi_include\Normalizer\Value;

use Drupal\Component\Utility\NestedArray;
use Drupal\jsonapi\JsonApiSpec;
use Drupal\jsonapi\Normalizer\Value\HttpExceptionNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\JsonApiDocumentTopLevelNormalizerValue as JsonApiDocumentTopLevelNormalizerValueBase;

/**
 * Helps normalize the top level document in compliance with the JSON API spec.
 */
class JsonApiDocumentTopLevelNormalizerValue extends JsonApiDocumentTopLevelNormalizerValueBase {

  /**
   * @inheritdoc
   */
  public function rasterizeValue() {
    // Create the array of normalized fields, starting with the URI.
    $rasterized = [
      'data' => [],
      'jsonapi' => [
        'version' => JsonApiSpec::SUPPORTED_SPECIFICATION_VERSION,
        'meta' => [
          'links' => ['self' => JsonApiSpec::SUPPORTED_SPECIFICATION_PERMALINK],
        ],
      ],
      'links' => [],
    ];

    foreach ($this->values as $normalizer_value) {
      if ($normalizer_value instanceof HttpExceptionNormalizerValue) {
        $previous_errors = NestedArray::getValue($rasterized, ['meta', 'errors']) ?: [];
        // Add the errors to the pre-existing errors.
        $rasterized['meta']['errors'] = array_merge($previous_errors, $normalizer_value->rasterizeValue());
      }
      else {
        $rasterized_value = $normalizer_value->rasterizeValue();
        if (array_key_exists('data', $rasterized_value) && array_key_exists('links', $rasterized_value)) {
          $rasterized['data'][] = $rasterized_value['data'];
          $rasterized['links'] = NestedArray::mergeDeep($rasterized['links'], $rasterized_value['links']);
        }
        else {
          $rasterized['data'][] = $rasterized_value;
        }
      }
    }
    // Deal with the single entity case.
    $rasterized['data'] = $this->isCollection ?
      array_filter($rasterized['data']) :
      reset($rasterized['data']);

    // Add the self link.
    if ($this->context['request']) {
      /* @var \Symfony\Component\HttpFoundation\Request $request */
      $request = $this->context['request'];
      $rasterized['links'] += [
        'self' => $this->linkManager->getRequestLink($request),
      ];
      // If this is a collection we need to append the pager data.
      if ($this->isCollection) {
        // Add the pager links.
        $rasterized['links'] += $this->linkManager->getPagerLinks($request, $this->linkContext);

        // Add the pre-calculated total count to the meta section.
        if (isset($this->context['total_count'])) {
          $rasterized = NestedArray::mergeDeepArray([
            $rasterized,
            ['meta' => ['count' => $this->context['total_count']]],
          ]);
        }
      }
    }

    // This is the top-level JSON API document, therefore the rasterized value
    // must include the rasterized includes: there is no further level to bubble
    // them to!
    $included = array_filter($this->rasterizeIncludes());
    if (!empty($included)) {
      foreach ($included as $included_item) {
        if ($included_item['data'] === FALSE) {
          unset($included_item['data']);
          $rasterized = NestedArray::mergeDeep($rasterized, $included_item);
        }
        else {
          $rasterized['included'][] = $included_item['data'];
        }
      }
    }

    if (empty($rasterized['links'])) {
      unset($rasterized['links']);
    }
    if ($this->isCollection){
      if (!empty($rasterized['included']) && !empty($rasterized['data'])) {
        $included_data = [];
        foreach ($rasterized['included'] as $item) {
          $included_data[$item['id']] = $item;
        }
        foreach ($rasterized['data'] as &$item) {
          foreach ($item['relationships'] as &$relationship) {
            if (isset($relationship['data'][0])) {
              foreach ($relationship['data'] as &$relation_item) {
                $id = $relation_item['id'];
                if (isset($included_data[$id])) {
                  $relation_item['data']['attributes'] = $included_data[$id]['attributes'];
                }
              }
            } else {
              $id = $relationship['data']['id'];
              if (isset($included_data[$id])) {
                $relationship['data']['attributes'] = $included_data[$id]['attributes'];
              }
            }
          }
        }
      }
    }
    return $rasterized;
  }
}
