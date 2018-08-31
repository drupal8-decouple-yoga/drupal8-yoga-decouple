<?php

namespace Drupal\jsonapi_extras\Normalizer;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\jsonapi\Normalizer\NormalizerBase;
use Drupal\jsonapi\Normalizer\FieldItemNormalizer as JsonapiFieldItemNormalizer;
use Drupal\jsonapi\Normalizer\Value\FieldItemNormalizerValue;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerManager;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Converts the Drupal field structure to a JSON API array structure.
 */
class FieldItemNormalizer extends NormalizerBase {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = FieldItemInterface::class;

  /**
   * The JSON API field normalizer entity.
   *
   * @var \Drupal\jsonapi\Normalizer\FieldItemNormalizer
   */
  protected $subject;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The field enhancer manager.
   *
   * @var \Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerManager
   */
  protected $enhancerManager;

  /**
   * Constructs a new FieldItemNormalizer.
   *
   * @param \Drupal\jsonapi\Normalizer\FieldItemNormalizer $subject
   *   The JSON API field normalizer entity.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerManager $enhancer_manager
   *   The field enhancer manager.
   */
  public function __construct(JsonapiFieldItemNormalizer $subject, EntityTypeManagerInterface $entity_type_manager, ResourceFieldEnhancerManager $enhancer_manager) {
    $this->subject = $subject;
    $this->entityTypeManager = $entity_type_manager;
    $this->enhancerManager = $enhancer_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    // First get the regular output.
    $normalized_output = $this->subject->normalize($object, $format, $context);
    // Then detect if there is any enhancer to be applied here.
    /** @var \Drupal\jsonapi_extras\ResourceType\ConfigurableResourceType $resource_type */
    $resource_type = $context['resource_type'];
    $enhancer = $resource_type->getFieldEnhancer($object->getParent()->getName());
    if (!$enhancer) {
      return $normalized_output;
    }
    // Apply any enhancements necessary.
    $processed = $enhancer->undoTransform($normalized_output->rasterizeValue());
    $normalized_output = new FieldItemNormalizerValue([$processed], new CacheableMetadata());

    return $normalized_output;
  }

  /**
   * {@inheritdoc}
   */
  public function setSerializer(SerializerInterface $serializer) {
    parent::setSerializer($serializer);
    $this->subject->setSerializer($serializer);
  }

}
