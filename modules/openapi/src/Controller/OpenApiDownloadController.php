<?php

namespace Drupal\openapi\Controller;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists OpenAPI direct downloads.
 *
 * @todo Should this just be menu items?
 */
class OpenApiDownloadController extends ControllerBase {

  /**
   * Current a OpenApiDownloadController.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  public $openApiGeneratorManager;

  /**
   * Creates a new OpenApiParamConverter.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $open_api_generator_manager
   *   The current openapi generator plugin manager instance.
   */
  public function __construct(PluginManagerInterface $open_api_generator_manager) {
    $this->openApiGeneratorManager = $open_api_generator_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.openapi.generator')
    );
  }

  /**
   * List all REST Doc pages.
   */
  public function downloadsList() {
    $message = '<h2>' . $this->t('OpenAPI files') . '</h2>';
    // @todo Which page should the docs link to?
    $message .= '<p>' . $this->t('The following links provide the REST or JSON API resources documented in <a href=":open_api_spec">OpenAPI(fka Swagger)</a> format.', [':open_api_spec' => 'https://github.com/OAI/OpenAPI-Specification/tree/OpenAPI.next']) . ' ';
    $message .= $this->t('This JSON file can be used in tools such as the <a href=":swagger_editor">Swagger Editor</a> to provide a more detailed version of the API documentation.', [':swagger_editor' => 'http://editor.swagger.io/#/']) . '</p>';

    $return['direct_download'] = [
      '#type' => 'markup',
      '#markup' => $message,
    ];

    $plugins = $this->openApiGeneratorManager->getDefinitions();
    if (count($plugins)) {
      $open_api_links = [];
      foreach ($plugins as $generator_id => $generator) {
        $open_api_links[$generator_id] = [
          'url' => Url::fromRoute('openapi.download', ['openapi_generator' => $generator_id], ['query' => ['_format' => 'json']]),
          'title' => $this->t(
            '%label OpenApi Schema Download',
            [
              '%label' => $this->openApiGeneratorManager->createInstance($generator_id)->getLabel(),
            ]),
        ];
      }
      // @todo create link non-entity rest downloads.
      $return['direct_download']['links'] = [
        '#theme' => 'links',
        '#links' => $open_api_links,
      ];
    }
    else {
      $links = [
        '%rest_link' => (new Link('Core REST', Url::fromUri('https://www.drupal.org/docs/8/core/modules/rest')))->toString(),
        '%jsonapi_link' => (new Link('JSON API', Url::fromUri('https://www.drupal.org/project/jsonapi')))->toString(),
      ];

      $no_plugins_message = '<strong>' . $this->t('No OpenApi generator plugins are currently available.') . '</strong> ';
      $no_plugins_message .= $this->t('You must enable a REST or API module which supports OpenApi Downloads, such as the %rest_link and %jsonapi_link modules.', $links);
      drupal_set_message(['#markup' => $no_plugins_message], 'warning');
    }

    return $return;
  }

}
