<?php

namespace Drupal\jsonapi_defaults;

use Drupal\jsonapi\Normalizer\JsonApiDocumentTopLevelNormalizer;
use Drupal\jsonapi\ResourceType\ResourceType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class JsonapiServiceOverride.
 *
 * @package Drupal\jsonapi_defaults
 */
class JsonApiDefaultsJsonApiDocumentTopLevelNormalizer extends JsonApiDocumentTopLevelNormalizer {

  /**
   * @inheritdoc
   */
  protected function expandContext(Request $request, ResourceType $resource_type) {
    // Do not return unrequested resources in include.
    // @see http://jsonapi.org/format/#fetching-includes
    if($request->query->get('include')) {
        return parent::expandContext($request, $resource_type);
    }

    /** @var \Drupal\jsonapi_extras\ResourceType\ConfigurableResourceType $resource_type */
    $resource_config = $resource_type->getJsonapiResourceConfig();

    $default_include = $resource_config->getThirdPartySetting('jsonapi_defaults', 'default_include');
    $includes = trim(implode(',', $default_include), ',');
    $request->query->set('include', $includes);

    return parent::expandContext($request, $resource_type);
  }

}
