<?php

namespace Drupal\jsonapi_extras\Normalizer;

use Drupal\jsonapi_extras\ResourceType\ConfigurableResourceType;
use Drupal\schemata_json_schema\Normalizer\jsonapi\FieldDefinitionNormalizer as SchemataJsonSchemaFieldDefinitionNormalizer;
use Drupal\jsonapi\ResourceType\ResourceTypeRepository;

/**
 * Applies field enhancer schema changes to field schema.
 */
class SchemaFieldDefinitionNormalizer extends SchemataJsonSchemaFieldDefinitionNormalizer {

  /**
   * The JSON API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepository
   */
  protected $resourceTypeRepository;

  /**
   * Constructs a SchemaFieldDefinitionNormalizer object.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepository $resource_type_repository
   *   A resource type repository.
   */
  public function __construct(ResourceTypeRepository $resource_type_repository) {
    $this->resourceTypeRepository = $resource_type_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    $normalized = parent::normalize($entity, $format, $context);

    // Load the resource type for this entity type and bundle.
    $resource_type = $this->resourceTypeRepository->get($context['entityTypeId'], $context['bundleId']);

    if (!$resource_type || !$resource_type instanceof ConfigurableResourceType) {
      return $normalized;
    }

    $field_name = $context['name'];
    $enhancer = $resource_type->getFieldEnhancer($field_name);
    if (!$enhancer) {
      return $normalized;
    }
    $original_field_schema = $normalized['properties']['attributes']['properties'][$field_name];
    $field_schema = &$normalized['properties']['attributes']['properties'][$field_name];
    $field_schema = $enhancer->getOutputJsonSchema();
    // Copy *some* properties from the original.
    $copied_properties = ['title', 'description'];
    foreach ($copied_properties as $property_name) {
      if (!empty($original_field_schema[$property_name])) {
        $field_schema[$property_name] = $original_field_schema[$property_name];
      }
    }

    return $normalized;
  }

}
