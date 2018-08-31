/**
 * @file
 * Provides Redoc integration.
 */

(function ($, Drupal) {
  console.log("teST");

  /**
   * Attach a behavior to initialize the redoc.
   *
   * Redoc should already be initilized as part of the the library loading. If
   * the `spec-url` attribute is not supplied, then the ui won't load. We want
   * to trigger the build manually since we want the UI to load.
   *
   * @TODO: Use a dynamic or calculated id, to allow for multiple instances of
   * the UI to be rendered on the same page.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior to initilize the swagger-ui.
   */
  Drupal.behaviors.swaggerui = {
    attach: function (context, settings) {
      /**
       * Define a swagger ui plugin to remove the top bar from the swagger ui.
       */
      var $redocElem = $('redoc');
      // If url is set, then redoc should initialize properly.
      var url = $redocElem.attr('spec-url');
        console.log(url);
      if (url === undefined) {
        var spec = $redocElem.attr('spec');
        if (spec !== undefined) {
          // If there is no url, then we load the UI using the spec attribute.
          Redoc.init(JSON.parse(spec));
        }
        else {
          console.log("Redoc spec not provided. UI not loaded.");
        }
      }
    }
  };

})(jQuery, Drupal);