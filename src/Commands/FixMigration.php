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
class FixMigration extends DrushCommands {

  /**
   */
  public function __construct() {
  }

  private function convertMediaEmbedsFromD7ToD9InEntity($entity, $embed_info) {
    $count = 1;
    $changed = FALSE;
    foreach ($embed_info['embeds'] as $field_name => $embeds) {
      $text = $entity->{$field_name}->value;
      $field_changed = FALSE;
      foreach ($embeds as $embed) {
        if (!empty($embed['new_code'])) {
          $text = str_replace($embed['code'], $embed['new_code'], $text, $count);
          $changed = TRUE;
          $field_changed = TRUE;
        }
      }
      if ($field_changed) {
        $entity->{$field_name}->value = $text;
      }
    }
    if ($changed) {
      $entity->save();
    }
  }

  /**
   * Convert D7 format media embeds to D9.
   * 
   * @command convert-d7-media-embeds-to-d9
   * 
   * @param string $entity_type
   *  Type of entity to work on. Usually node.
   * @param string $field_types
   *  Comma separated names of field types to work on. Usually "text,text_long,text_with_summary"
   */
  public function convertMediaEmbedsFromD7ToD9($entity_type, $field_types, $id = NULL) {
    $field_types = explode(',', $field_types);
    $wwm_media_utility = \Drupal::service('wwm_utility.media');

    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type);

    if (empty($id)) {
      $media_embeds = $wwm_media_utility->findD7MediaEmbeds($entity_type, $field_types);
      foreach ($media_embeds as $id => $embed_info) {
        $entity = $entity_storage->load($embed_info['id']);
        $this->convertMediaEmbedsFromD7ToD9InEntity($entity, $embed_info);
      }
    }
    else {
      $wwm_field_utility = \Drupal::service('wwm_utility.field');
      $fields = $wwm_field_utility->findFilesOfType($field_types, $entity_type);
      $entity = $entity_storage->load($id);
      $media_embeds_on_node = $wwm_media_utility->findD7MediaEmbedsInEntity($entity, $fields[$entity_type][$entity->bundle()]);
      $this->convertMediaEmbedsFromD7ToD9InEntity($entity, $media_embeds_on_node);
    }
  }
}
