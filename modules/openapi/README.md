# OpenApi Module

This module provides a [OpenAPI](https://github.com/OAI/OpenAPI-Specification)
(A.K.A. Swagger) compliant resource describing the enabled REST resources on
about Drupal Site.

This module supports and integrates with Drupal Core's REST endpoints and
the [JsonApi](https://drupal.org/project/jsonapi) module.

## Setup

This module can be installed as [any other Drupal 8 module]
https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules).

### Module Dependencies

The OpenAPI module leverages the [schemata](https://drupal.org/project/schemata)
module to derive the entity schema for the project.

## Documentation

### What is OpenAPI?

Openapi is a standard for documenting api functionality.

[Learn more about Open API](https://github.com/OAI/OpenAPI-Specification).

### Using OpenAPI

In order to view the schema documents you will need to have a supported module
enabled in your site. The module provides support for the REST module, found in
Drupal Core, and the contributed [JsonAPI module](https://www.drupal.org/project/jsonapi).
Once a supported module is installed, you can navigate to the schema url to
view the entity schema. For the two supplied modules, these urls are below.

- REST - /openapi/rest?_format=json
- JsonAPI - /openapi/json?_format=json

If you don't have one of these modules enabled, you will need to do so in order
to use the functionality provided by this module.

### Viewing OpenAPI Schema In the UI

This module uses the [OpenAPI UI module](https://drupal.org/project/openapi_ui)
to display the generated docs within a web interface. You can install openapi_ui
and its extention modules.

We recommend that you use the [Redoc](https://github.com/Rebilly/ReDoc) project.
This can be downloaded and configured to display docs within a drupal site using
the [Redoc for OpenAPI UI](https://drupal.org/project/openapi_ui_redoc) module.
Once the module installed, you will need to have a supported api schema module
nstalled, see "Using OpenAPI" above. You can then navigate to the respective url
for the api.

- REST - `/admin/config/services/openapi/redoc/rest`
- JsonAPI -  `/admin/config/services/openapi/redoc/jsonapi`

### Adding support for a custom api

You can write additional integrations for other rest modules and contributed
functionality. OpenAPI leverages plugins to detect and generate the download
documents. Please take a look a the source for the jsonapi and rest module
integrations, which are found in this module. For support and assistance with
custom integrations, please open a support request issue for the OpenAPI module
on Drupal.org.
