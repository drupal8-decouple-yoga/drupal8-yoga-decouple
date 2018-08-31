<?php

namespace Drupal\jsonapi_extras\Plugin\jsonapi\FieldEnhancer;

use Drupal\jsonapi_extras\Plugin\DateTimeEnhancerBase;
use Shaper\Util\Context;

/**
 * Perform additional manipulations to datetime fields.
 *
 * @ResourceFieldEnhancer(
 *   id = "date_time_from_string",
 *   label = @Translation("Date Time (Date Time field)"),
 *   description = @Translation("Formats a date based the configured date format for date fields.")
 * )
 */
class DateTimeFromStringEnhancer extends DateTimeEnhancerBase {

  /**
   * {@inheritdoc}
   */
  protected function doUndoTransform($data, Context $context) {
    $storage_timezone = new \DateTimezone(DATETIME_STORAGE_TIMEZONE);
    $date = new \DateTime($data, $storage_timezone);

    $configuration = $this->getConfiguration();

    $output_timezone = new \DateTimezone(drupal_get_user_timezone());
    $date->setTimezone($output_timezone);

    return $date->format($configuration['dateTimeFormat']);
  }

  /**
   * {@inheritdoc}
   */
  protected function doTransform($data, Context $context) {
    $date = new \DateTime($data);

    // Adjust the date for storage.
    $storage_timezone = new \DateTimezone(DATETIME_STORAGE_TIMEZONE);
    $date->setTimezone($storage_timezone);

    return $date->format(DATETIME_DATETIME_STORAGE_FORMAT);
  }

}
