<?php

namespace Drupal\wwm_utility\Commands;

use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commands related to images
 */
class Image extends DrushCommands {

  /**
   * To copy missing title text from a field to title property of a image field.
   *
   * @param string $entity_type
   *  Entity type
   * @param string $bundle
   *  Entity bundle
   * @param string $image_field
   *  Image field to be updated
   * @param string $title_text_field
   *  Title text field to copy value from
   *
   * @command wwm:image-field-copy-title-text
   */
  public function copyTitleTextToImageField($entity_type, $bundle, $image_field, $title_text_field) {
    $io = new SymfonyStyle($this->input, $this->output);
  
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_definition = $entity_type_manager->getDefinition($entity_type);
    $entity_storage = $entity_type_manager->getStorage($entity_type);

    $result = $entity_storage->getQuery()
      ->allRevisions()
      ->condition($entity_definition->getKey('bundle'), $bundle)
      // Source field value exists
      ->exists($title_text_field)
      // Target title property of the image field not set
      ->condition($image_field . '.title', '')
      ->accessCheck(FALSE)
      ->execute();

    $count = 0;
    foreach ($result as $revision_id => $entity_id) {
      $revision = $entity_storage->loadRevision($revision_id);
      $title = $revision->{$title_text_field}->value;
      $revision->{$image_field}->title = $title;
      $revision->setNewRevision(FALSE);
      // Set syncing so no new revision will be created by content moderation process.
      // @see Drupal\content_moderation\Entity\Handler\ModerationHandler::onPresave()
      $revision->setSyncing(TRUE);
      $revision->save();
      $count++;
      $io->text("{$count}. Updated {$revision->label()}({$revision->id()})");
    }

    $io->success("Updated $count items.");
  }
}
