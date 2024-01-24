<?php

namespace Drupal\wwm_utility\Commands;

use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commands related to images
 */
class Image extends DrushCommands {

  /**
   * To copy text from a field to property of an image field.
   * To use on image fields that were having separate fields in D7 for text and alt properties
   *
   * @param string $entity_type
   *  Entity type
   * @param string $bundle
   *  Entity bundle
   * @param string $image_field
   *  Image field to be updated
   * @param string $source_field
   *  Source text field to copy value from
   *
   * @command wwm:image-field-populate-property
   */
  public function copyPropertyTextToImageField($entity_type, $bundle, $image_field, $source_field, $image_field_property) {
    $io = new SymfonyStyle($this->input, $this->output);
  
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_definition = $entity_type_manager->getDefinition($entity_type);
    $entity_storage = $entity_type_manager->getStorage($entity_type);

    $result = $entity_storage->getQuery()
      ->allRevisions()
      ->condition($entity_definition->getKey('bundle'), $bundle)
      // Source field value exists
      ->exists($source_field)
      // Target property of the image field not set
      ->condition($image_field . '.' . $image_field_property, '')
      ->accessCheck(FALSE)
      ->execute();

    $count = 0;
    foreach ($result as $revision_id => $entity_id) {
      /** @var \Drupal\entity\ContentEntityInterface $revision */
      $revision = $entity_storage->loadRevision($revision_id);
      $value = $revision->{$source_field}->value;
      $revision->{$image_field}->{$image_field_property} = $value;
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
