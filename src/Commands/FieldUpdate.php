<?php

namespace Drupal\wwm_utility\Commands;

use Drush\Commands\DrushCommands;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Output\ConsoleOutput;
use Drupal\Core\Entity\ContentEntityInterface;

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
   * Set to use only a text format on a entity bundle.
   *
   * @command wwm:set-single-text-format-on-field
   *
   * @param string $entity_type
   *  Type of entity to work on. Usually node.
   * @param string $bundle
   *  Type of bundle to work on. It can be more than one with comma separated.
   * @param string $field
   *  Formatted text fields to act on. It can be more than one with comma separated.
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
        $this->setTextFormatOnField($revision, $fields, $format, $revision_id);
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
          $this->setTextFormatOnField($revision, $fields, $format, $revision_id);
        }
      }
    }
  }

  protected function setTextFormatOnField(ContentEntityInterface $entity, $fields, $format, $revision_id) {
    $entity_changed = FALSE;
    foreach ($fields as $field) {
      if ($entity->{$field}->value && $entity->{$field}->format != $format) {
        $entity->{$field}->format = $format;
        $entity_changed = TRUE;
      }
    }
    if ($entity_changed) {
      $this->logger()->notice(dt('Saving content after setting text format "@title"...', ['@title' => $entity->label()]));
      // There is a bug that prevents saving a change in field when it matches with default revision.
      // This is a workaround to that problem got from https://www.drupal.org/project/drupal/issues/2859042#comment-13083066
      $entity_storage = \Drupal::entityTypeManager()->getStorage($entity->getEntityType()->id());
      $entity->original = $entity_storage->loadRevision($revision_id);

      $pathauto_exists = \Drupal::moduleHandler()->moduleExists('pathauto');

      if ($pathauto_exists && !in_array($entity->getEntityTypeId(), ['paragraph'])) {
        $entity->path->pathauto = \Drupal\pathauto\PathautoState::SKIP;
      }
      $entity->setNewRevision(FALSE);
      // Set syncing so no new revision will be created by content moderation process.
      // @see Drupal\content_moderation\Entity\Handler\ModerationHandler::onPresave()
      $entity->setSyncing(TRUE);
      $entity->save();
    }
  }

  /**
   * This is utility method mainly built to help self::getTextFormatUsage()
   */
  public static function getTextFormatUsageInEntityContents($format, $field_types, $entity_type, $bundles = []) {
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_storage = $entity_type_manager->getStorage($entity_type);
    $entity_definition = $entity_type_manager->getDefinition($entity_type);

    $wwm_field_utility = \Drupal::service('wwm_utility.field');

    $fields = $wwm_field_utility->findFilesOfType($field_types, $entity_type);

    $query = \Drupal::entityQuery($entity_type);
    if (!empty($bundles)) {
      $query->condition($entity_definition->getKey('bundle'), $bundles, 'IN');
    }

    $results = $query->execute();

    $usage = [];
    foreach ($results as $entity_id) {
      $entity = $entity_storage->load($entity_id);
      if (!empty($fields[$entity_type][$entity->bundle()])) {

        $usage[$entity_id]['bundle'] = $entity->bundle();
        $entity_type_entity = $entity->getEntityType();

        $revisions = $entity_storage->getQuery()
          ->allRevisions()
          ->condition($entity_type_entity->getKey('id'), $entity->id())
          ->sort($entity_type_entity->getKey('revision'), 'DESC')
          ->execute();

        foreach ($revisions as $revision_id => $entity_id) {
          $revision = $entity_storage->loadRevision($revision_id);

          foreach ($fields[$entity_type][$entity->bundle()] as $field) {
            if ($revision->{$field}->format == $format) {
              $usage[$entity_id]['revisions'][$revision_id][] = $field;
            }
          }
        }
        if (empty($usage[$entity_id]['revisions'])) {
          unset($usage[$entity_id]);
        }
      }
    }
    return $usage;
  }

  /**
   * Get usage of a text format on content.
   *
   * @command wwm:get-text-format-usage
   *
   * @param string $format
   *  Format to be queried for.
   * @param string $field_types
   *  Comma separated names of field types to work on. Usually "text,text_long,text_with_summary"
   * @param string $entity_type
   *  Type of entity to work on. Usually node.
   * @param string $bundle
   *  Type of bundle to work on. It can be more than one with comma separated.
   */
  public function getTextFormatUsage($format, $field_types, $entity_type, $bundle = NULL) {

    $field_types = explode(',', $field_types);

    $bundles = [];
    if (!empty($bundle)) {
      $bundles = explode(',', $bundle);
    }

    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_definition = $entity_type_manager->getDefinition($entity_type);

    $types = \Drupal::entityTypeManager()
      ->getStorage($entity_definition->getBundleEntityType())
      ->loadMultiple();

    /** @var \Drupal\wwm_utility\FieldUtility $wwm_field_utility */
    $wwm_field_utility = \Drupal::service('wwm_utility.field');

    $fields = $wwm_field_utility->findFilesOfType($field_types, $entity_type);

    $usage = self::getTextFormatUsageInEntityContents($format, $field_types, $entity_type, $bundles);

    if (empty($bundles)) {
      $types = \Drupal::entityTypeManager()
      ->getStorage($entity_definition->getBundleEntityType())
      ->loadMultiple();
      $bundles = [];
      foreach ($types as $type_name => $type) {
        $bundles[] = $type_name;
      }
    }

    $usage_in_field_settings_rows = [];
    if (\Drupal::moduleHandler()->moduleExists('allowed_formats')) {
      foreach ($bundles as $type) {
        if (!empty($fields[$entity_type][$type])) {
          foreach ($fields[$entity_type][$type] as $field) {
            $field_instance = FieldConfig::loadByName($entity_type, $type, $field);
            $allowed_formats = $field_instance->getThirdPartySetting('allowed_formats', 'allowed_formats');
            if ($allowed_formats && in_array($format, $allowed_formats)) {
              $usage_in_field_settings_rows[] = [$type, $field];
            }
          }
        }
      }
    }


    $output = new ConsoleOutput();
    $use_in_field_settings_table = new Table($output);
    $use_in_field_settings_table->setHeaderTitle(t('Use in Field Settings'));
    $use_in_field_settings_table
      ->setHeaders(['Bundle', 'Field'])
      ->setRows($usage_in_field_settings_rows);
    if (empty($usage_in_field_settings_rows)) {
      $use_in_field_settings_table->addRow([ new TableCell(t('No usage found'), ['colspan' => 2])]);
    }
    $use_in_field_settings_table->setColumnWidth(0, 11);
    $use_in_field_settings_table->setColumnWidth(1, 11);
    $use_in_field_settings_table->render();


    $use_in_entities_table = new Table($output);
    $use_in_entities_table->setHeaderTitle(t('Use in Entities'));
    $use_in_entities_table
      ->setHeaders(['Entity ID', 'Bundle', 'Revision', 'Fields']);
    foreach ($usage as $entity_id => $use) {
      $revision_index = 0;
      foreach ($use['revisions'] as $revision_id => $fields) {
        if ($revision_index == 0) {
          $use_in_entities_table->addRow([
            new TableCell($entity_id, ['rowspan' => count($use['revisions'])]),
            new TableCell($use['bundle'], ['rowspan' => count($use['revisions'])]),
            $revision_id,
            implode(", ", $fields)
          ]);
        }
        else {
          $use_in_entities_table->addRow([$revision_id, implode(", ", $fields)]);
        }
        $revision_index++;
      }
    }

    if (empty($usage)) {
      $use_in_entities_table->addRow([ new TableCell(t('No usage found'), ['colspan' => 4])]);
    }
    $use_in_entities_table->setColumnWidth(0, 4);
    $use_in_entities_table->setColumnWidth(1, 4);
    $use_in_entities_table->setColumnWidth(2, 4);
    $use_in_entities_table->setColumnWidth(3, 4);
    $use_in_entities_table->render();
  }

  /**
   * Replace usage of a text format with another one.
   *
   * @command wwm:replace-text-format-usage
   *
   * @param string $format_old
   *  Format to be replaced.
   * @param string $format_new
   *  Format to be replaced with.
   * @param string $field_types
   *  Comma separated names of field types to work on. Usually "text,text_long,text_with_summary"
   * @param string $entity_type
   *  Type of entity to work on. Usually node.
   * @param string $bundle
   *  Type of bundle to work on. It can be more than one with comma separated.
   */
  public function replaceTextFormatUsage($format_old, $format_new, $field_types, $entity_type, $bundle = NULL) {

    $field_types = explode(',', $field_types);

    $bundles = [];
    if (!empty($bundle)) {
      $bundles = explode(',', $bundle);
    }

    $usage = self::getTextFormatUsageInEntityContents($format_old, $field_types, $entity_type, $bundles);

    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_storage = $entity_type_manager->getStorage($entity_type);

    foreach ($usage as $entity_id => $old_format_usage_data) {
      foreach ($old_format_usage_data['revisions'] as $revision_id => $fields) {
        $entity_revision = $entity_storage->loadRevision($revision_id);
        $this->setTextFormatOnField($entity_revision, $fields, $format_new, $revision_id);
      }
    }
  }

  /**
   * This command helps to simply save contents. Mainly to cause recomputing of computed fields.
   *
   * @command wwm:re-save-contents
   *
   * @param string $entity_type
   *  Entity type
   * @param string $bundle
   *  Optional. Type of bundle to work on.
   */
  public function reSaveContents($entity_type, $bundle = NULL) {
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_definition = $entity_type_manager->getDefinition($entity_type);
    $entity_storage = $entity_type_manager->getStorage($entity_type);

    $query = \Drupal::entityQuery($entity_type);
    if ($bundle) {
      $query->condition($entity_definition->getKey('bundle'), $bundle);
    }
    // We don't want to process any item with id '0'. Specifically user 0 is anonymous user
    $query->condition($entity_definition->getKey('id'), 0, '>');
    $results = $query->sort($entity_definition->getKey('id') , 'ASC')
      ->range(0, 1)
      ->accessCheck(FALSE)
      ->execute();
    if (!empty($results)) {
      $next_id = reset($results);
      $pathauto_exists = \Drupal::moduleHandler()->moduleExists('pathauto');

      do {
        $this->logger()->notice(dt('Loading and saving item with id "@id"...', ['@id' => $next_id]));

        /** @var \Drupal\entity\ContentEntityInterface $entity */
        $entity = $entity_storage->load($next_id);
        if ($entity_definition->hasKey('revision')) {
          $entity->setNewRevision(FALSE);
          // Set syncing so no new revision will be created by content moderation process.
          // @see Drupal\content_moderation\Entity\Handler\ModerationHandler::onPresave()
          $entity->setSyncing(TRUE);
        }
        if ($pathauto_exists) {
          $entity->path->pathauto = \Drupal\pathauto\PathautoState::SKIP;
        }
        $entity->save();

        $query = \Drupal::entityQuery($entity_type);
        if ($bundle) {
          $query->condition($entity_definition->getKey('bundle'), $bundle);
        }
        $query->condition($entity_definition->getKey('id'), $next_id, '>');
        $results = $query->sort($entity_definition->getKey('id') , 'ASC')
          ->range(0, 1)
          ->accessCheck(FALSE)
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

  /**
   * Remove a field
   *
   * @command wwm:remove-field
   */
  public function removeField($entity_type, $bundle, $field_name) {
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_definition = $entity_type_manager->getDefinition($entity_type);
    $entity_storage = $entity_type_manager->getStorage($entity_type);

    // Deleting field.
    $field_config = FieldConfig::loadByName($entity_type, $bundle, $field_name);
    if ($field_config) {
      // Reduce file usage count.
      if ($field_config->getType() == 'file' || $field_config->getType() == 'image') {
        $query = \Drupal::entityQuery($entity_type);
        if ($entity_type != 'user') {
          // TODO: Improve this code to dynamiclly find whether entity type has bundles or not.
          $query->condition($entity_definition->getKey('bundle'), $bundle);
        }
        // $results = $query->sort($entity_definition->getKey('id') , 'ASC')
        //   ->range(0, 1)
        //   ->execute();
        $results = $query->execute();
        foreach ($results as $entity_id) {
          /** @var \Drupal\entity\ContentEntityInterface $entity */
          $entity = $entity_storage->load($entity_id);
          if ($entity) {
            $field_items = $entity->get($field_name);
            foreach ($field_items as $field_item) {
              $file = File::load($field_item->target_id);
              if ($file) {
                \Drupal::service('file.usage')->delete($file, 'file', $entity->getEntityTypeId(), $entity->id());
                $this->logger()->notice(dt('Removed usage of file @fid from @entity_type:@entity_id', ['@fid' => $file->id(), '@entity_type' => $entity_type, '@entity_id' => $entity->id()]));
              }
            }
          }
        }
      }
      $field_config->delete();
      $this->logger()->notice(dt('Deleted field @field of entity @entity of bundle @bundle', ['@field' => $field_name, '@entity' => $entity_type, '@bundle' => $bundle]));

      // Deleting field storage.
      $field_storage_config = FieldStorageConfig::loadByName($entity_type, $field_name);
      if ($field_storage_config) {
        $bundles = $field_storage_config->getBundles();
        if (empty($bundles)) {
          $field_storage_config->delete();
          $this->logger()->notice(dt('Deleted field storage'));
        }
      }
    }
    else {
      $this->logger()->notice(dt('Field @field of entity @entity of bundle @bundle does not exist', ['@field' => $field_name, '@entity' => $entity_type, '@bundle' => $bundle]));
    }
  }

  /**
   * Copy values from on field to another in same entity.
   *
   * @command wwm:copy-field
   */
  public function copyField($entity_type, $bundle, $source_field, $destination_field) {
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_definition = $entity_type_manager->getDefinition($entity_type);
    $entity_storage = $entity_type_manager->getStorage($entity_type);

    $compatible_field_types = [
      'string' => [
        'text_long',
      ],
      'string_long' => [
        'text_long',
      ]
    ];

    $source_field_config = FieldConfig::loadByName($entity_type, $bundle, $source_field);
    $destination_field_config = FieldConfig::loadByName($entity_type, $bundle, $destination_field);

    if (!$source_field_config) {
      $this->logger()->error(dt('Source field @field does not exist!', [
        '@field' => $source_field,
      ]));
    }
    if (!$destination_field_config) {
      $this->logger()->error(dt('Destination field @field does not exist!', [
        '@field' => $destination_field,
      ]));
    }

    if ($source_field_config && $destination_field_config) {

      if ($source_field_config->getType() != $destination_field_config->getType() &&
        (
          !isset($compatible_field_types[$source_field_config->getType()]) ||
          !in_array($destination_field_config->getType(), $compatible_field_types[$source_field_config->getType()])
        )
      ) {
        $this->logger()->error(dt('Cannot copy field of type @source_type to field of type @destination_type.', [
          '@source_type' => $source_field_config->getType(),
          '@destination_type' => $destination_field_config->getType(),
        ]));
      }
      else {
        $pathauto_exists = \Drupal::moduleHandler()->moduleExists('pathauto');

        $query = \Drupal::entityQuery($entity_type);
        if ($entity_type != 'user') {
          // TODO: Improve this code to dynamiclly find whether entity type has bundles or not.
          $query->condition($entity_definition->getKey('bundle'), $bundle);
        }
        $query->exists($source_field);
        $results = $query->execute();
        if (empty($results)) {
          $this->logger()->notice(dt('Not items to copy'));
        }
        foreach ($results as $entity_id) {
          /** @var \Drupal\entity\ContentEntityInterface $entity */
          $entity = $entity_storage->load($entity_id);
          if ($entity) {
            /** @var \Drupal\Core\Field\FieldItemList $field_items */
            $field_items = $entity->get($source_field);
            $entity->set($destination_field, $field_items->getValue());

            if ($entity_definition->hasKey('revision')) {
              $entity->setNewRevision(FALSE);
              // Set syncing so no new revision will be created by content moderation process.
              // @see Drupal\content_moderation\Entity\Handler\ModerationHandler::onPresave()
              $entity->setSyncing(TRUE);
            }
            if ($pathauto_exists && !in_array($entity_type, ['paragraph'])) {
              $entity->path->pathauto = \Drupal\pathauto\PathautoState::SKIP;
            }
            $entity->save();
            $this->logger()->notice(dt('Copied for "@label" (@id).', ['@label' => $entity->label(), '@id' => $entity->id()]));
          }
        }
      }
    }
    else {
      $this->logger()->notice(dt('Could not find either or both of fields.'));
    }
  }

  /**
   * List fields of type in an entity type.
   *
   * @command wwm:list-fields
   * @param string $entity_type
   *  Entity type
   * @param string $field_types
   *  Field types
   */
  public function listFields($entity_type, $field_types) {
    /** @var \Drupal\wwm_utility\FieldUtility $wwm_field_utility */
    $wwm_field_utility = \Drupal::service('wwm_utility.field');

    $field_types = explode(',', $field_types);

    $fields = $wwm_field_utility->findFilesOfType($field_types, $entity_type);
    $rows = [];
    foreach ($fields[$entity_type] as $bundle => $bundle_fields) {
      $rows[] = [$bundle, implode("\n", $bundle_fields)];
    }
    $output = new ConsoleOutput();
    $table = new Table($output);
    $table
      ->setHeaders(['Bundle', 'Fields'])
      ->setRows($rows);
    $table->render();
  }
}
