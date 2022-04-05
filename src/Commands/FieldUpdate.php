<?php

namespace Drupal\wwm_utility\Commands;

use Drush\Commands\DrushCommands;

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
class FieldUpdate extends DrushCommands {

  /**
   */
  public function __construct() {
  }

  /**
   * Convert D7 format media embeds to D9.
   * 
   * @command set-single-text-format-on-field
   * 
   * @param string $entity_type
   *  Type of entity to work on. Usually node.
   * @param string $bundle
   *  Type of bundle to work on. It can be more than one with comma separated.
   * @param string $field
   *  Formetted text fields to act on. It can be more than one with comma separated.
   *  Please note: It won't test if the field has no value.
   * @param string $format
   *  Format to set on the field.
   * @param int $entity_id
   *  Optional. If provided, on this item will be updated.
   */
  public function setSingleTextFormatOnField($entity_type, $bundle, $field, $format, $entity_id = NULL) {
    $bundles = explode(',', $bundle);
    $fields = explode(',', $field);

    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_definition = $entity_type_manager->getDefinition($entity_type);
    $entity_storage = $entity_type_manager->getStorage($entity_type);


    if ($entity_id) {
      $entity = $entity_storage->load($entity_id);
      $entity_type = $entity->getEntityType();

      $revisions = $entity_storage->getQuery()
        ->allRevisions()
        ->condition($entity_type->getKey('id'), $entity->id())
        ->sort($entity_type->getKey('revision'), 'DESC')
        ->execute();

      foreach ($revisions as $revision_id => $entity_id) {
        $revision = $entity_storage->loadRevision($revision_id);
        $this->setTextFormatOnField($revision, $fields, $format);
      }
      // $entity = $entity_storage->load($entity_id);
    }
    else {
      $query = \Drupal::entityQuery($entity_type)
        ->condition($entity_definition->getKey('bundle'), $bundles, 'IN');
      $results = $query->execute();

      foreach ($results as $entity_id) {
        $entity = $entity_storage->load($entity_id);
        $entity_type = $entity->getEntityType();

        $revisions = $entity_storage->getQuery()
          ->allRevisions()
          ->condition($entity_type->getKey('id'), $entity->id())
          ->sort($entity_type->getKey('revision'), 'DESC')
          ->execute();

        foreach ($revisions as $revision_id => $entity_id) {
          $revision = $entity_storage->loadRevision($revision_id);
          $this->setTextFormatOnField($revision, $fields, $format);
        }
      }
    }
  }

  protected function setTextFormatOnField($entity, $fields, $format) {
    $entity_changed = FALSE;
    foreach ($fields as $field) {
      if ($entity->{$field}->value && $entity->{$field}->format != $format) {
        $entity->{$field}->format = $format;
        $entity_changed = TRUE;
      }
    }
    if ($entity_changed) {
      $entity->save();
    }
  }
}
