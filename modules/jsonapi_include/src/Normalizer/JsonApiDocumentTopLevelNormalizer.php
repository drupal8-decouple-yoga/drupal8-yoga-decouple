<?php

namespace Drupal\jsonapi_include\Normalizer;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\jsonapi\Normalizer\JsonApiDocumentTopLevelNormalizer as JsonApiDocumentTopLevelNormalizerBase;
use Drupal\jsonapi\Resource\EntityCollection;
use Drupal\jsonapi_include\Normalizer\Value\JsonApiDocumentTopLevelNormalizerValue;

/**
 * @see \Drupal\jsonapi\Resource\JsonApiDocumentTopLevel
 */
class JsonApiDocumentTopLevelNormalizer extends JsonApiDocumentTopLevelNormalizerBase {

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $data = $object->getData();
    if (empty($context['expanded'])) {
      $context += $this->expandContext($context['request'], $context['resource_type']);
    }

    if ($data instanceof EntityReferenceFieldItemListInterface) {
      $normalizer_values = [
        $this->serializer->normalize($data, $format, $context),
      ];
      $link_context = ['link_manager' => $this->linkManager];
      return new JsonApiDocumentTopLevelNormalizerValue($normalizer_values, $context, $link_context, FALSE);
    }
    $is_collection = $data instanceof EntityCollection;
    $include_count = $context['resource_type']->includeCount();
    // To improve the logical workflow deal with an array at all times.
    $entities = $is_collection ? $data->toArray() : [$data];
    $context['has_next_page'] = $is_collection ? $data->hasNextPage() : FALSE;

    if ($include_count) {
      $context['total_count'] = $is_collection ? $data->getTotalCount() : 1;
    }
    $serializer = $this->serializer;
    $normalizer_values = array_map(function ($entity) use ($format, $context, $serializer) {
      return $serializer->normalize($entity, $format, $context);
    }, $entities);

    $link_context = [
      'link_manager' => $this->linkManager,
      'has_next_page' => $context['has_next_page'],
    ];

    if ($include_count) {
      $link_context['total_count'] = $context['total_count'];
    }

    return new JsonApiDocumentTopLevelNormalizerValue($normalizer_values, $context, $link_context, $is_collection);
  }
}
