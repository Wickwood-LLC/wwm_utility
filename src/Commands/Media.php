<?php

namespace Drupal\wwm_utility\Commands;

use Drush\Commands\DrushCommands;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Symfony\Component\Console\Terminal;

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
class Media extends DrushCommands {
  /**
   * Convert D7 format media embeds to D9.
   * 
   * @command wwm-utility:find-image-style-for-embeds
   * 
   * @param string $entity_type
   *  Type of entity to work on. Usually node.
   * @param string $field_types
   *  Comma separated names of field types to work on. Usually "text,text_long,text_with_summary"
   */
  public function findImageStylesForEmbeds($entity_type, $field_types, $image_style = NULL) {
    $field_types = explode(',', $field_types);
    /** @var \Drupal\wwm_utility\MediaUtility $wwm_media_utility */
    $wwm_media_utility = \Drupal::service('wwm_utility.media');

    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type);
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_definition = $entity_type_manager->getDefinition($entity_type);

    $media_embeds = $wwm_media_utility->findImageStylesInMediaEmbeds($entity_type, $field_types, $image_style );
    $this->logger()->notice(dt('Prepared list of @entity_type revisions to work on.', ['@entity_type' => $entity_type]));

    $formatterManager = new FormatterManager();

    foreach ($media_embeds as $id => $revision_embeds) {
      foreach ($revision_embeds as $revision_id => $embeds) {

        if ($entity_definition->hasKey('revision')) {
          $entity_revision = $entity_storage->loadRevision($revision_id);
        }
        else {
          // Loading non-revisionable entity. Please note it is not revision id here, it is entity id itself.
          $entity_revision = $entity_storage->load($revision_id);
        }

        $entity_data = [
          'Entity Type' => $entity_type,
          'Entity ID' => $id,
          'Revision ID' => $revision_id,
          'Title' => $entity_revision->label(),
        ];

        $opts = [
          FormatterOptions::INCLUDE_FIELD_LABELS => TRUE,
          // FormatterOptions::TABLE_STYLE => 'compact',
          FormatterOptions::TERMINAL_WIDTH => self::getTerminalWidth(),
        ];
        $formatterOptions = new FormatterOptions([], $opts);
        $formatterManager->write($this->output(), 'table', new PropertyList($entity_data), $formatterOptions);
        // $this->convertMediaEmbedsFromD7ToD9InEntity($entity_revision, $embeds, $revision_id);
        // $this->logger()->notice(dt("Entity Type: @entity_type\nEntity ID: @id\nRevision ID: @revision\nTitle: \"@title\"", ['@revision' => $revision_id, '@entity_type' => $entity_type, '@id' => $id, '@title' => $entity_revision->label()]));


        $row_data = [];
        foreach ($embeds as $field => $data) {
          foreach ($data as $image_style => $count) {
            $row_data[] = ['Field' => $field, 'Image Style' => $image_style, 'Count' => $count];
          }
        }

        $opts = [
          FormatterOptions::INCLUDE_FIELD_LABELS => TRUE,
          // FormatterOptions::TABLE_STYLE => 'compact',
          FormatterOptions::TERMINAL_WIDTH => self::getTerminalWidth(),
        ];
        $formatterOptions = new FormatterOptions([], $opts);
        $formatterManager->write($this->output(), 'table', new RowsOfFields($row_data), $formatterOptions);
        $this->output()->writeln('====================================================');
      }
    }
  }

  public static function getTerminalWidth(): int
    {
        $term = new Terminal();
        return $term->getWidth();
    }
}
