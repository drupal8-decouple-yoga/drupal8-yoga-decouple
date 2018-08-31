<?php

namespace Drupal\jsonapi_extras\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Defines the JSON API Resource Config entity.
 *
 * @ConfigEntityType(
 *   id = "jsonapi_resource_config",
 *   label = @Translation("JSON API Resource Config"),
 *   handlers = {
 *     "list_builder" = "Drupal\jsonapi_extras\JsonapiResourceConfigListBuilder",
 *     "form" = {
 *       "add" = "Drupal\jsonapi_extras\Form\JsonapiResourceConfigForm",
 *       "edit" = "Drupal\jsonapi_extras\Form\JsonapiResourceConfigForm",
 *       "delete" = "Drupal\jsonapi_extras\Form\JsonapiResourceConfigDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\jsonapi_extras\JsonapiResourceConfigHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "jsonapi_resource_config",
 *   admin_permission = "administer site configuration",
 *   static_cache = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/config/services/jsonapi/add/{entity_type_id}/{bundle}",
 *     "edit-form" = "/admin/config/services/jsonapi/{jsonapi_resource_config}/edit",
 *     "delete-form" = "/admin/config/services/jsonapi/{jsonapi_resource_config}/delete",
 *     "collection" = "/admin/config/services/jsonapi"
 *   }
 * )
 */
class JsonapiResourceConfig extends ConfigEntityBase {

  /**
   * The JSON API Resource Config ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The path for the resource.
   *
   * @var string
   */
  protected $path;

  /**
   * The type for the resource.
   *
   * @var string
   */
  protected $resourceType;

  /**
   * Resource fields.
   *
   * @var array
   */
  protected $resourceFields = [];

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    static::rebuildRoutes();
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);
    static::rebuildRoutes();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    $id = explode('--', $this->id);
    $typeManager = $this->entityTypeManager();
    $dependency = $typeManager->getDefinition($id[0])->getBundleConfigDependency($id[1]);
    $this->addDependency($dependency['type'], $dependency['name']);
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);
    // The add-form route depends on entity_type_id and bundle.
    if (in_array($rel, ['add-form'])) {
      $parameters = explode('--', $this->id);
      $uri_route_parameters['entity_type_id'] = $parameters[0];
      $uri_route_parameters['bundle'] = $parameters[1];
    }
    return $uri_route_parameters;
  }

  /**
   * Triggers rebuilding of JSON API routes.
   */
  protected static function rebuildRoutes() {
    try {
      \Drupal::service('jsonapi.resource_type.repository')->reset();
      \Drupal::service('router.builder')->setRebuildNeeded();
    }
    catch (ServiceNotFoundException $exception) {}
  }

}
