<?php

namespace Drupal\jsonapi_extras\Normalizer;

use Drupal\jsonapi\ResourceType\ResourceType;

/**
 * Common code for entity normalizers.
 */
trait EntityNormalizerTrait {

  /**
   * Prepares the input data to create the entity.
   *
   * @param array $data
   *   The input data to modify.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   Contains the info about the resource type.
   *
   * @return array
   *   The modified input data.
   */
  protected function prepareInput(array $data, ResourceType $resource_type) {
    /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface[] $field_storage_definitions */
    $field_storage_definitions = \Drupal::service('entity_field.manager')
      ->getFieldStorageDefinitions(
        $resource_type->getEntityTypeId()
      );
    $data_internal = [];
    /** @var \Drupal\jsonapi_extras\ResourceType\ConfigurableResourceType $resource_type */
    // Translate the public fields into the entity fields.
    foreach ($data as $public_field_name => $field_value) {
      // Skip any disabled field.
      if (!$resource_type->isFieldEnabled($public_field_name)) {
        continue;
      }
      $internal_name = $resource_type->getInternalName($public_field_name);
      $enhancer = $resource_type->getFieldEnhancer($public_field_name, 'publicName');

      if (isset($field_storage_definitions[$internal_name])) {
        $field_storage_definition = $field_storage_definitions[$internal_name];
        if ($field_storage_definition->getCardinality() === 1) {
          try {
            $field_value = $enhancer ? $enhancer->transform($field_value) : $field_value;
          }
          catch (\TypeError $exception) {
            $field_value = NULL;
          }
        }
        elseif (is_array($field_value)) {
          foreach ($field_value as $key => $individual_field_value) {
            try {
              $field_value[$key] = $enhancer ? $enhancer->transform($individual_field_value) : $individual_field_value;
            }
            catch (\TypeError $exception) {
              $field_value[$key] = NULL;
            }
          }
        }
      }

      $data_internal[$internal_name] = $field_value;
    }

    return $data_internal;
  }

  /**
   * Get the configuration entity based on the entity type and bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle_id
   *   The bundle ID.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The resource config entity or NULL.
   */
  protected function getResourceConfig($entity_type_id, $bundle_id) {
    $id = sprintf('%s--%s', $entity_type_id, $bundle_id);
    // TODO: Inject this service.
    $resource_config = \Drupal::entityTypeManager()
      ->getStorage('jsonapi_resource_config')
      ->load($id);

    return $resource_config;
  }

}
