<?php

namespace Drupal\jsonapi_extras\Normalizer;

use Drupal\jsonapi\Normalizer\ConfigEntityNormalizer as JsonapiConfigEntityNormalizer;
use Drupal\jsonapi\ResourceType\ResourceType;

/**
 * Override ConfigEntityNormalizer to prepare input.
 */
class ConfigEntityNormalizer extends JsonapiConfigEntityNormalizer {

  use EntityNormalizerTrait;

  /**
   * {@inheritdoc}
   */
  protected function getFields($entity, $bundle, ResourceType $resource_type) {
    $enabled_public_fields = parent::getFields($entity, $bundle, $resource_type);
    // Then detect if there is any enhancer to be applied here.
    foreach ($enabled_public_fields as $field_name => &$field_value) {
      $enhancer = $resource_type->getFieldEnhancer($field_name);
      if (!$enhancer) {
        continue;
      }
      $field_value = $enhancer->undoTransform($field_value);
    }

    return $enabled_public_fields;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareInput(array $data, ResourceType $resource_type) {
    foreach ($data as $public_field_name => &$field_value) {
      /** @var \Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerInterface $enhancer */
      $enhancer = $resource_type->getFieldEnhancer($public_field_name);
      if (!$enhancer) {
        continue;
      }
      $field_value = $enhancer->transform($field_value);
    }

    return parent::prepareInput($data, $resource_type);
  }

}
