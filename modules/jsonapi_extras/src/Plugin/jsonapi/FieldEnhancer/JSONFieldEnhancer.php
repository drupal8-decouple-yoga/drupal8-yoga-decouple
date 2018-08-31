<?php

namespace Drupal\jsonapi_extras\Plugin\jsonapi\FieldEnhancer;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shaper\Util\Context;


/**
 * Perform additional manipulations to JSON fields.
 *
 * @ResourceFieldEnhancer(
 *   id = "json",
 *   label = @Translation("JSON Field"),
 *   description = @Translation("Render JSON Field has real json")
 * )
 */
class JSONFieldEnhancer extends ResourceFieldEnhancerBase implements ContainerFactoryPluginInterface {

  /**
   * @var Drupal\Component\serialization\Json
   */
  protected $encoder;

  /**
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Component\Serialization\Json $encoder
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, Json $encoder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->encoder = $encoder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('serialization.json'));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function doUndoTransform($data, Context $context) {
    return $this->encoder->decode($data);
  }

  /**
   * {@inheritdoc}
   */
  protected function doTransform($data, Context $context) {
    return $this->encoder->encode($data);
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputJsonSchema() {
    return [
      'oneOf' => [
        ['type' => 'object'],
        ['type' => 'array'],
        ['type' => 'null']
      ]
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $resource_field_info) {
    return [];
  }

}
