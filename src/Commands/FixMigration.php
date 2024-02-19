<?php

namespace Drupal\wwm_utility\Commands;

use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use Drush\Commands\DrushCommands;
use Drupal\wwm_utility\MediaUtility;

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

  private function convertMediaEmbedsFromD7ToD9InEntity($entity, $embed_info, $revision_id) {
    $changed = FALSE;
    foreach ($embed_info as $field_name => $embeds) {
      $text = $entity->{$field_name}->value;

      // Replace previous message if any. To protect from having message more than once.
      $text = str_replace(MediaUtility::MEDIA_MISSING_MESSAGE, '', $text);

      $field_changed = FALSE;
      foreach ($embeds as $embed) {
        if (!empty($embed['new_code'])) {
          $text = str_replace($embed['code'], $embed['new_code'], $text);
          $changed = TRUE;
          $field_changed = TRUE;
        }
      }
      if ($field_changed) {
        $entity->{$field_name}->value = $text;
      }
    }
    if ($changed) {
      // There is a bug that prevents saving a change in field when it matches with default revision.
      // This is a workaround to that problem got from https://www.drupal.org/project/drupal/issues/2859042#comment-13083066
      $entity_storage = \Drupal::entityTypeManager()->getStorage($entity->getEntityType()->id());
      $entity_type_manager = \Drupal::entityTypeManager();
      $entity_definition = $entity_type_manager->getDefinition($entity->getEntityType()->id());
      if ($entity_definition->hasKey('revision')) {
        $entity->original = $entity_storage->loadRevision($revision_id);

        $entity->setNewRevision(FALSE);
      }
      // Set syncing so no new revision will be created by content moderation process.
      // @see Drupal\content_moderation\Entity\Handler\ModerationHandler::onPresave()
      $entity->setSyncing(TRUE);
      $entity->save();
    }
  }

  /**
   * Convert D7 format media embeds to D9.
   * 
   * @command wwm:convert-d7-media-embeds-to-d9
   * 
   * @param string $entity_type
   *  Type of entity to work on. Usually node.
   * @param string $field_types
   *  Comma separated names of field types to work on. Usually "text,text_long,text_with_summary"
   * @option id Optional entity id to run only for one entity.
   */
  public function convertMediaEmbedsFromD7ToD9(
    $entity_type, $field_types,
    array $options = ['id' => NULL, 'report' => NULL]) {

    $id = $options['id'];
    $report = $options['report'];

    $field_types = explode(',', $field_types);
    /** @var \Drupal\wwm_utility\MediaUtility $wwm_media_utility */
    $wwm_media_utility = \Drupal::service('wwm_utility.media');

    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type);
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_definition = $entity_type_manager->getDefinition($entity_type);

    $media_embeds = $wwm_media_utility->findD7MediaEmbeds($entity_type, $field_types, $id);
    $this->logger()->notice(dt('Prepared list of @entity_type revisions to work on.', ['@entity_type' => $entity_type]));
    if (!isset($report)) {
      foreach ($media_embeds as $id => $embed_info) {
        foreach ($embed_info['embeds'] as $revision_id => $embeds) {
          $this->logger()->notice(dt('Converting embed codes on @revision of @entity_type @id"...', ['@revision' => $revision_id, '@entity_type' => $entity_type, '@id' => $id]));
          if ($entity_definition->hasKey('revision')) {
            $entity_revision = $entity_storage->loadRevision($revision_id);
          }
          else {
            // Loading non-revisionable entity. Please note it is not revision id here, it is entity id itself.
            $entity_revision = $entity_storage->load($revision_id);
          }
          $this->convertMediaEmbedsFromD7ToD9InEntity($entity_revision, $embeds, $revision_id);
        }
      }
    }
    else {
      $module_handler = \Drupal::service('module_handler');
      $module_path = $module_handler->getModule('wwm_utility')->getPath();

      $loader = new FilesystemLoader([$module_path . '/templates']);
      $twig = new Environment($loader);

      $report_file = fopen($report, 'w');
      fwrite(
        $report_file,
        $twig->render(
          'd7-media-embeds-info-report.html.twig',
          [
            'embed_info' => $media_embeds,
          ]
        )
      );
      fclose($report_file);
    }
  }
}
