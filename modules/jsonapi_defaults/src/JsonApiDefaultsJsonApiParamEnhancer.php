<?php

namespace Drupal\jsonapi_defaults;

use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\jsonapi\Routing\JsonApiParamEnhancer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * JsonApiDefaultsJsonApiParamEnhancer class.
 *
 * @internal
 */
class JsonApiDefaultsJsonApiParamEnhancer extends JsonApiParamEnhancer {

  /**
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(DenormalizerInterface $filter_normalizer, DenormalizerInterface $sort_normalizer, DenormalizerInterface $page_normalizer, ConfigManagerInterface $configManager) {
    parent::__construct($filter_normalizer, $sort_normalizer, $page_normalizer);
    $this->configManager = $configManager;
  }

  /**
   * {@inheritdoc}
   */
  public function enhance(array $defaults, Request $request) {
    /** @var \Symfony\Component\Routing\Route $route */
    $route = $defaults['_route_object'];
    if ($route->hasRequirement('_entity_type') && $route->hasRequirement('_bundle')) {
      if ($resource_config = $this->configManager->loadConfigEntityByName('jsonapi_extras.jsonapi_resource_config.' . $route->getRequirement('_entity_type') . '--' . $route->getRequirement('_bundle'))) {
        $thirdPartyDefaults = $resource_config->getThirdPartySetting('jsonapi_defaults', 'default_filter');

        $default_filter = [];
        if (is_array($thirdPartyDefaults)) {
          foreach ($thirdPartyDefaults as $key => $value) {
            if (substr($key, 0, 6) === 'filter') {
              $key = str_replace('filter:', '', $key);
              $this->setFilterValue($default_filter, $key, $value);
            }
          }
        }
        $filters = array_merge($default_filter, $request->query->get('filter') ?? []);

        if (!empty($filters)) {
          $request->query->set('filter', $filters);
        }
      }
    };

    return parent::enhance($defaults, $request);

  }

  /**
   * Set filter into nested array.
   *
   * @param $arr
   * @param $path
   * @param $value
   */
  private function setFilterValue(&$arr, $path, $value) {
    $keys = explode("_", $path);

    foreach ($keys as $key) {
      $arr = &$arr[$key];
    }

    $arr = $value;
  }

}
