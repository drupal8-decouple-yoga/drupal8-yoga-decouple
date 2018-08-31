<?php

namespace Drupal\jsonapi_extras\Normalizer;

use Drupal\jsonapi\Normalizer\ContentEntityNormalizer as JsonapiContentEntityNormalizer;

/**
 * Override ContentEntityNormalizer to prepare input.
 */
class ContentEntityNormalizer extends JsonapiContentEntityNormalizer {

  use EntityNormalizerTrait;

}
