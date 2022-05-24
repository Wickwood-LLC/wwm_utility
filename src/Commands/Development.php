<?php

namespace Drupal\wwm_utility\Commands;

use Drush\Commands\DrushCommands;
// use Drupal\wwm_utility\MediaUtility;

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
class Development extends DrushCommands {

  protected $d9_database;

  protected $d7_database;

  /**
   */
  public function __construct() {
    $this->d7_database = \Drupal\Core\Database\Database::getConnection('default', 'migrate');

    // Ensure connection to default database.
    \Drupal\Core\Database\Database::setActiveConnection();
    $this->d9_database = \Drupal::database();
  }

  public function __destruct() {
    // Reset connection to default database.
    \Drupal\Core\Database\Database::setActiveConnection();
  }

  /**
   * This command helps to simply save contents. Mainly to cause recomputing of computed fields.
   *
   * @command wwm:dev-invalidate-user-email-addresses
   */
  public function reSaveContents() {
    $entity_type = 'user';
  
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_definition = $entity_type_manager->getDefinition($entity_type);
    $entity_storage = $entity_type_manager->getStorage($entity_type);

    $query = \Drupal::entityQuery($entity_type);
    
    // We don't want to process any item with id '0'. Specifically user 0 is anonymous user
    $query->condition($entity_definition->getKey('id'), 0, '>');
    $results = $query->sort($entity_definition->getKey('id') , 'ASC')
      ->range(0, 1)
      ->execute();
    if (!empty($results)) {
      $next_id = reset($results);

      do {
        $entity = $entity_storage->load($next_id);
        $this->logger()->notice(dt('Setting dummy email address for "@user" with id "@id"...', ["@user" => $entity->label(), '@id' => $next_id]));
        $entity->setEmail($entity->getAccountName() . '.' . $entity->id() . '@noemail.invalid');
        if ($entity_definition->hasKey('revision')) {
          $entity->setNewRevision(FALSE);
          // Set syncing so no new revision will be created by content moderation process.
          // @see Drupal\content_moderation\Entity\Handler\ModerationHandler::onPresave()
          $entity->setSyncing(TRUE);
        }
        $entity->save();

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
