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
class Revision extends DrushCommands {

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

  public function getExtraRevisionsOfNode($nid) {
    $d7_vids = $this->d7_database->query("SELECT vid FROM {node_revision} WHERE nid = :nid", [':nid' => $nid])->fetchCol();
    $d9_vids = $this->d9_database->query("SELECT vid FROM {node_revision} WHERE nid = :nid", [':nid' => $nid])->fetchCol();

    return array_diff($d9_vids, $d7_vids);
  }

  public function getCurrentRevisionIDOfNodeInD7($nid) {
    return $this->d7_database->query("SELECT vid FROM {node} WHERE nid = :nid", [':nid' => $nid])->fetchField();
  }

  public function setActiveRevisionForNode($nid, $revision_id) {
    // $this->d9_database->update('node')
    //   ->fields([
    //     'vid' => $revision_id,
    //   ])
    //   ->condition('nid', $nid)
    //   ->execute();

    // $this->d9_database->update('node_field_data')
    //   ->fields([
    //     'vid' => $revision_id,
    //   ])
    //   ->condition('nid', $nid)
    //   ->execute();

    $entity_type = 'node';
    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type);

    $revision = $entity_storage->loadRevision($revision_id);

    $revision->isDefaultRevision(TRUE);
    $revision->setNewRevision(FALSE);
    // Set syncing so no new revision will be created by content moderation process.
    // @see Drupal\content_moderation\Entity\Handler\ModerationHandler::onPresave()
    $revision->setSyncing(TRUE);
    $revision->save();
  }

  public function discardExtraRevisionsAndRestForOneNode($nid) {
    $extra_revision_ids = $this->getExtraRevisionsOfNode($nid);

    if (!empty($extra_revision_ids)) {
      $current_revision_id_on_d7 = $this->getCurrentRevisionIDOfNodeInD7($nid);
      $this->setActiveRevisionForNode($nid, $current_revision_id_on_d7);

      $entity_type = 'node';
      $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type);

      foreach ($extra_revision_ids as $revision_id) {
        $entity_storage->deleteRevision($revision_id);
      }
    }
  }

  /**
   * Discard all node revisions appeared in this site and reset auto increment vid key as same as D7
   * 
   * @command wwm:discard-exgra-node-revisions-and-reset
   * 
   * @param int $nid
   *  Optional. If provided, only revision of this particular node will be deleted and reset.
   */
  public function discardExtraRevisionsAndRest($nid = NULL) {
  
    if (!$nid) {
      $nid = $this->d9_database->query("SELECT nid FROM {node} ORDER BY nid ASC LIMIT 1")->fetchField();

      do {
        $this->logger()->notice(dt('Processing node with ID @nid...', ['@nid' => $nid]));
        $this->discardExtraRevisionsAndRestForOneNode($nid);
        
        $nid = $this->d9_database->query("SELECT nid FROM {node} WHERE nid > :nid ORDER BY nid ASC LIMIT 1", [':nid' => $nid])->fetchField();
      } while ($nid);
    }
    else {
      $this->logger()->notice(dt('Processing node with ID @nid...', ['@nid' => $nid]));
      $this->discardExtraRevisionsAndRestForOneNode($nid);
    }
  }
}
