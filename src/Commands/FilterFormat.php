<?php

namespace Drupal\wwm_utility\Commands;

use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 */
class FilterFormat extends DrushCommands {

  /**
   */
  public function __construct() {}

  /**
   * List migrations that are executed
   *
   * @command wwm:filter-format-set-fallback
   * 
   * @param string $format
   *  Machine name of the filter format to set as the default one.
   */
  public function filterFormatSetFallback($format) {
    $output = new ConsoleOutput();

    $entity_type_manager = \Drupal::entityTypeManager();
    $filter_format_storage = $entity_type_manager->getStorage('filter_format');

    $format_object = $filter_format_storage->load($format);
    if (!$format_object) {
      $output->writeln('<error>' . dt('Filter format "@format" does not exist!', ['@format' => $format]) . '</error>');
      return self::EXIT_FAILURE;
    }
    if ($format == \Drupal::config('filter.settings')->get('fallback_format')) {
      $output->writeln('<info>' . dt('"@format" is already the fallback format.', ['@format' => $format]) . '</info>');
      return self::EXIT_FAILURE_WITH_CLARITY;
    }
    else {
      $filter_settings = \Drupal::configFactory()->getEditable('filter.settings');
      $filter_settings->set('fallback_format', $format);
      $filter_settings->save();
      $output->writeln('<info>' . dt('"@format" has been set as fallback format.', ['@format' => $format]) . '</info>');
    }
  }

}
