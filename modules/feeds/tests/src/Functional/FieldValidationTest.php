<?php

namespace Drupal\Tests\feeds\Functional;

/**
 * Tests fields validation.
 *
 * @group feeds
 */
class FieldValidationTest extends FeedsBrowserTestBase {

  /**
   * Tests text field validation.
   */
  public function testTextFieldValidation() {
    $this->createFieldWithStorage('field_alpha', [
      'storage' => [
        'settings' => [
          'max_length' => 5,
        ],
      ],
    ]);

    // Create and configure feed type.
    $feed_type = $this->createFeedType([
      'parser' => 'csv',
      'custom_sources' => [
        'guid' => [
          'label' => 'guid',
          'value' => 'guid',
          'machine_name' => 'guid',
        ],
        'title' => [
          'label' => 'title',
          'value' => 'title',
          'machine_name' => 'title',
        ],
        'alpha' => [
          'label' => 'alpha',
          'value' => 'alpha',
          'machine_name' => 'alpha',
        ],
      ],
      'mappings' => array_merge($this->getDefaultMappings(), [
        [
          'target' => 'field_alpha',
          'map' => ['value' => 'alpha'],
        ],
      ]),
    ]);

    // Import CSV file.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => $this->resourcesUrl() . '/csv/content.csv',
    ]);
    $this->batchImport($feed);

    // Import CSV file.
    $this->assertText('Created 1 Article.');
    $this->assertText('Failed importing 1 Article.');
    $this->assertText("The content Ut wisi enim ad minim veniam failed to validate with the following errors");
    $this->assertText('field_alpha.0.value: field_alpha label: the text may not be longer than 5 characters.');
  }

}
