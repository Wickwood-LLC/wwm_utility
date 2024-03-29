<?php

namespace Drupal\wwm_utility\Commands;

use Drupal\Core\Database\Database;
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

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $d9_database;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $d7_database;

  /**
   */
  public function __construct() {
    // Ensure connection to default database.
    Database::setActiveConnection();
    $this->d9_database = \Drupal::database();
  }

  public function __destruct() {
    // Reset connection to default database.
    Database::setActiveConnection();
  }

  public function getD7Database() {
    if (!$this->d7_database) {
      if (Database::getConnectionInfo('migrate')) {
        $this->d7_database = Database::getConnection('default', 'migrate');
      }
    }
    return $this->d7_database;
  }

  /**
   * List migrations that are executed
   *
   * @command wwm:list-migrations-executed
   * @option executed List only executed migrations.
   * @option commands List migrate import commands
   */
  public function listMigrations($group = 'migrate_drupal_7', array $options = ['executed' => TRUE, 'commands' => FALSE]) {
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

    $output = new ConsoleOutput();$output = new ConsoleOutput();
    if (!$options['commands'])  {
      $table = new Table($output);
      $table
        ->setHeaders(['Migration', 'Last Imported'])
        ->setRows($migrations_processed);
      $table->render();
    }
    else {
      foreach ($migrations_processed as $migration) {
        $output->writeln('drush migrate-import ' . $migration['migration_id'] . ' --execute-dependencies --group="' . $group . '"');
      }
    }

    print("Total $count migrations.") . PHP_EOL;
  }

  /**
   * List migrations that are executed
   *
   * @command wwm:list-entities-not-migrated
   *
   * @param string $entity_type
   *  Type of entity to work on. Usually node.
   */
  public function missingEntities($entity_type, $bundle) {

    $d7_entity_table_map = [
      'field_collection' => 'field_collection_item',
    ];

    $d7_entity_id_map = [
      'field_collection' => 'item_id',
    ];

    $d7_entity_bundle_column_map = [
      'field_collection' => 'field_name',
    ];

    $d9_entity_type_map = [
      'field_collection' => 'paragraph',
    ];

    $d7_database = $this->getD7Database();

    $d7_query = $d7_database->select($d7_entity_table_map[$entity_type], 'entity');
    $d7_query->fields('entity', [$d7_entity_id_map[$entity_type]]);
    $d7_query->condition('entity.' . $d7_entity_bundle_column_map[$entity_type], '%' . $bundle . '%', 'LIKE');
    $d7_ids = $d7_query->execute()->fetchCol();

    // print_r($d7_ids);

    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_storage = $entity_type_manager->getStorage($d9_entity_type_map[$entity_type] ?? $entity_type);

    // $entity_definition = $this->entityTypeManager->getDefinition($entity_type);
    $entity_definition = $entity_storage->getEntityType();
    $d9_result = $entity_storage->getQuery()
      ->condition($entity_definition->getKey('bundle'), $bundle)
      ->accessCheck(FALSE)
      ->execute();

    $d9_ids = array_values($d9_result);

    print_r(array_diff($d7_ids, $d9_ids));
  }

}
