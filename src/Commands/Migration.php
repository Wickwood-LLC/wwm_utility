<?php

namespace Drupal\wwm_utility\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Component\Datetime\DateTimePlus;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://git.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://git.drupalcode.org/devel/tree/drush.services.yml
 */
class Migration extends DrushCommands {

  protected $d9_database;

  /**
   */
  public function __construct() {
    // Ensure connection to default database.
    \Drupal\Core\Database\Database::setActiveConnection();
    $this->d9_database = \Drupal::database();
  }

  public function __destruct() {
    // Reset connection to default database.
    \Drupal\Core\Database\Database::setActiveConnection();
  }

  /**
   * List migrations that are executed
   *
   * @command wwm:list-migrations-executed
   * @option executed List only executed migrations.
   */
  public function listMigrations($group = 'migrate_drupal_7', array $options = ['executed' => TRUE]) {
    $entity_type = 'migration';

    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_storage = $entity_type_manager->getStorage($entity_type);

    $query = \Drupal::entityQuery($entity_type);

    $query->condition('migration_group', $group);
    // $query->sort($entity_definition->getKey('id') , 'ASC');
    $results = $query->execute();

    $migrate_last_imported_store = \Drupal::keyValue('migrate_last_imported');

    $count = 0;

    $migrations = [];
    foreach ($results as $migration_name) {
      $migration = $entity_storage->load($migration_name);
      $last_imported = $migrate_last_imported_store->get($migration->id(), FALSE);
      if (empty($last_imported) && $options['executed']) {
        continue;
      }
      $count++;

      $migrations[$migration->id()] = [
        'migration_id' => $migration->id(),
        'last_imported' => $last_imported,
        'last_imported_formatted' => DateTimePlus::createFromTimestamp($last_imported / 1000)->format('c'),
      ];
    }

    usort($migrations, function ($item1, $item2) {
      return $item1['last_imported'] <=> $item2['last_imported'];
    });

    $migrations_processed = $migrations;

    foreach ($migrations_processed as &$row) {
      unset($row['last_imported']);
    }

    $output = new ConsoleOutput();
    $table = new Table($output);
    $table
      ->setHeaders(['Migration', 'Last Imported'])
      ->setRows($migrations_processed);
    $table->render();

    print("Total $count migrations.") . PHP_EOL;
  }

}
