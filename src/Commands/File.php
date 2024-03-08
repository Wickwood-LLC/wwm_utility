<?php

namespace Drupal\wwm_utility\Commands;

use Drupal\Core\Database\Database;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Style\SymfonyStyle;

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
class File extends DrushCommands {

  protected $d9_database;

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

  /**
   * Find file entities having file names not matching its mime type.
   *
   * @command wwm:file-find-invalid-filenames
   *
   * @option autocorrect Automatically correct file names.
   * @option ask Ask to rename for each of the findings. autocorrect will be disabled if this is set.
   */
  public function findInvalidFileNames(array $options = ['autocorrect' => FALSE, 'ask' => FALSE]) {
    $io = new SymfonyStyle($this->input, $this->output);

    $entity_type = 'file';
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_definition = $entity_type_manager->getDefinition($entity_type);
    $entity_storage = $entity_type_manager->getStorage($entity_type);

    $query = \Drupal::entityQuery($entity_type);

    $index = 1;
    
    // We don't want to process any item with id '0'. Specifically user 0 is anonymous user
    // $query->condition($entity_definition->getKey('id'), 0, '>');
    $results = $query->sort($entity_definition->getKey('id') , 'ASC')
      ->range(0, 1)
      ->execute();
    if (!empty($results)) {
      $next_id = reset($results);

      do {
        /** @var \Drupal\file\Entity\File $entity */
        $entity = $entity_storage->load($next_id);
        $guesser = \Drupal::service('file.mime_type.guesser.extension');
        $mime_type_from_filename = $guesser->guess($entity->filename->value);
        if ($mime_type_from_filename == 'application/octet-stream') {
          $extension = $guesser->convertMimeTypeToExtension($entity->getMimeType());
          if ($extension) {
            $new_name = $entity->filename->value . '.' . $extension;
          }
          $this->logger()->notice(dt( $index++ . '. Invalid file name "@filename" with id "@id". Proposed new name: @newname', ["@filename" => $entity->filename->value, '@id' => $next_id, '@newname' => $new_name]));
          if ($extension) {
            $rename = FALSE;
            if ($options['ask'] && $io->ask('Do you want to apply this name change?', FALSE)) {
              $rename = TRUE;
            }
            else if ($options['autocorrect']) {
              $rename = TRUE;
            }
            if ($rename) {
              $entity->filename = $new_name;
              $entity->save();
              $this->logger()->notice(dt("\tApplied new name."));
            }
          }
        }

        // $this->logger()->notice(dt('Setting dummy email address for "@user" with id "@id"...', ["@user" => $entity->label(), '@id' => $next_id]));

        $query = \Drupal::entityQuery($entity_type);
        $query->condition($entity_definition->getKey('id'), $next_id, '>');
        $results = $query->sort($entity_definition->getKey('id') , 'ASC')
          ->range(0, 1)
          ->execute();

        if (!empty($results)) {
          $next_id = reset($results);
        }
        else {
          $next_id = NULL;
        }

      } while ($next_id);
    }
  }
}
