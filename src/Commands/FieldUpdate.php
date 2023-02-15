<?php

namespace Drupal\wwm_utility\Commands;

use Drush\Commands\DrushCommands;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;

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

  protected function setTextFormatOnField($entity, $fields, $format, $revision_id) {
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

      $entity->setNewRevision(FALSE);
      // Set syncing so no new revision will be created by content moderation process.
      // @see Drupal\content_moderation\Entity\Handler\ModerationHandler::onPresave()
      $entity->setSyncing(TRUE);
      $entity->save();
    }
  }

  /**
   * Get usage of a text format on content.
   *
   * @command get-text-format-usage
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

    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_definition = $entity_type_manager->getDefinition($entity_type);
    $entity_storage = $entity_type_manager->getStorage($entity_type);

    $types = \Drupal::entityTypeManager()
      ->getStorage($entity_definition->getBundleEntityType())
      ->loadMultiple();

    $wwm_field_utility = \Drupal::service('wwm_utility.field');

    $fields = $wwm_field_utility->findFilesOfType($field_types, $entity_type);

    $query = \Drupal::entityQuery($entity_type);
    if ($bundle) {
      $bundles = explode(',', $bundle);
      $query->condition($entity_definition->getKey('bundle'), $bundles, 'IN');
    }
    else {
      $types = \Drupal::entityTypeManager()
      ->getStorage($entity_definition->getBundleEntityType())
      ->loadMultiple();
      $bundles = [];
      foreach ($types as $type_name => $type) {
        $bundles[] = $type_name;
      }
    }

    $usage_in_field_settings = [];
    if (\Drupal::moduleHandler()->moduleExists('allowed_formats')) {
      foreach ($bundles as $type) {
        if (!empty($fields[$entity_type][$type])) {
          foreach ($fields[$entity_type][$type] as $field) {
            $field_instance = FieldConfig::loadByName($entity_type, $type, $field);
            $allowed_formats = $field_instance->getThirdPartySetting('allowed_formats', 'allowed_formats');
            if ($allowed_formats && in_array($format, $allowed_formats)) {
              $usage_in_field_settings[$entity_type][$type][] = $field;
            }
          }
        }
      }
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

    $module_handler = \Drupal::service('module_handler');
    $module_path = $module_handler->getModule('wwm_utility')->getPath();

    $loader = new \Twig\Loader\FilesystemLoader([$module_path . '/templates']);
    $twig = new \Twig\Environment($loader);

    $file_handle = fopen('text-format-usage-report.html', 'w');
    fwrite(
      $file_handle,
      $twig->render(
        'text-format-usage-report.html.twig',
        [
          'entity_type' => $entity_type,
          'format' => $format,
          'usage_in_configuration' => $usage_in_field_settings,
          'usage_in_content' => $usage,
        ]
      )
    );
    fclose($file_handle);
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
      ->execute();
    if (!empty($results)) {
      $next_id = reset($results);

      do {
        $this->logger()->notice(dt('Loading and saving item with id "@id"...', ['@id' => $next_id]));

        $entity = $entity_storage->load($next_id);
        if ($entity_definition->hasKey('revision')) {
          $entity->setNewRevision(FALSE);
          // Set syncing so no new revision will be created by content moderation process.
          // @see Drupal\content_moderation\Entity\Handler\ModerationHandler::onPresave()
          $entity->setSyncing(TRUE);
        }
        $entity->save();

        $query = \Drupal::entityQuery($entity_type);
        if ($bundle) {
          $query->condition($entity_definition->getKey('bundle'), $bundle);
        }
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
   * Remove a field
   *
   * @command wwm:copy-field
   */
  public function copyField($entity_type, $bundle, $source_field, $destination_field) {
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_definition = $entity_type_manager->getDefinition($entity_type);
    $entity_storage = $entity_type_manager->getStorage($entity_type);

    // Deleting field.
    $source_field_config = FieldConfig::loadByName($entity_type, $bundle, $source_field);
    $destination_field_config = FieldConfig::loadByName($entity_type, $bundle, $destination_field);
    if ($source_field_config && $destination_field_config) {

      if ($source_field_config->getType() != $destination_field_config->getType()) {
        $this->logger()->error(dt('Cannot copy field of type @source_type to field of type @destination_type.', [
          '@source_type' => $source_field_config->getType(),
          '@destination_type' => $destination_field_config->getType(),
        ]));
      }
      else {
        $query = \Drupal::entityQuery($entity_type);
        if ($entity_type != 'user') {
          // TODO: Improve this code to dynamiclly find whether entity type has bundles or not.
          $query->condition($entity_definition->getKey('bundle'), $bundle);
        }
        $query->exists($source_field);
        $results = $query->execute();
        foreach ($results as $entity_id) {
          $entity = $entity_storage->load($entity_id);
          if ($entity) {
            $field_items = $entity->get($source_field);
            $entity->set($$field_items);
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
}
