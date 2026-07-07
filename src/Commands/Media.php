<?php

namespace Drupal\wwm_utility\Commands;

use Drush\Commands\DrushCommands;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Symfony\Component\Console\Terminal;
use Drush\Attributes as CLI;

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
  /**
   * Replace crop type reference in crops.
   */
  #[CLI\Command(name: 'wwm:replace-crop-type-reference-of-crops', aliases: [])]
  public function replaceCropTypeReferenceInCrops($from, $to): int {
    $entity_type_manager = \Drupal::entityTypeManager();

    $results = $entity_type_manager->getStorage('crop')->getQuery()
      ->condition('type', $from)
      ->accessCheck(TRUE)
      ->execute();
    foreach ($results as $id) {
      /** @var \Drupal\crop\Entity\Crop $crop */
      $crop = $entity_type_manager->getStorage('crop')->load($id);
      $crop->set('type', $to);
      $crop->save();
      print "Processed crop with id $id.\n";
    }
    return static::EXIT_SUCCESS;
  }

  public function _findFormattedTextFields(string $entity_type): array {
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
    $entityFieldManager = \Drupal::service('entity_field.manager');

    $fields = [];

    $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type);

    foreach (array_keys($bundles) as $bundle) {
        $definitions = $entityFieldManager->getFieldDefinitions($entity_type, $bundle);

        foreach ($definitions as $field_name => $definition) {
            $type = $definition->getType();

            if (in_array($type, [
                'text',
                'text_long',
                'text_with_summary',
            ], TRUE)) {
                $fields[$bundle][$field_name] = [
                    'label' => $definition->getLabel(),
                    'type' => $type,
                    'required' => $definition->isRequired(),
                ];
            }
        }
    }
    return $fields;
  }

   /**
   * Find formatted text fields.
   */
  #[CLI\Command(name: 'wwm:media-embeds-replace-linkit-field-name', aliases: [])]
  public function mediaEmbedsReplaceLinkitFieldName(string $entity_type): int {

    $entity_type_manager = \Drupal::entityTypeManager();

    $entity_storage = $entity_type_manager->getStorage($entity_type);
    $entity_type_definition = $entity_type_manager->getDefinition($entity_type);

    $query = \Drupal::entityQuery($entity_type)
      ->accessCheck(FALSE);

    $fields = $this->_findFormattedTextFields($entity_type);

    
    foreach ($fields as $bundle => $filed_list) {
      $limit = 100;
      $offset = 0;
      do {
        $query_copy = clone $query;
        // Add bundle condition only if the entity type supports bundles.
        if ($bundle !== NULL && $bundleKey = $entity_type_definition->getKey('bundle')) {
            $query_copy->condition($bundleKey, $bundle);
        }
        $ids = $query_copy->range($offset, $limit)
            ->sort($entity_type_definition->getKey('id'))
            ->execute();

        if (!$ids) {
            break;
        }

        $entities = $entity_storage->loadMultiple($ids);

        foreach ($entities as $entity) {
          // $this->output()->writeln("Loaded {$entity->id()}: {$entity->label()}");
          foreach ($filed_list as $field_name => $field_info) {
            $field = $entity->get($field_name);
            if (! $field->isEmpty()) {
              $changed = false;
              $value = $field->value;
              $new_value = $this->changeLinkitFieldNameInMediaEmbeddings($value, $changed);
              if ($changed) {
                // $this->output()->writeln('======================================');
                // $this->output()->writeln($value);
                // $this->output()->writeln('**************************************');
                // $this->output()->writeln($new_value);
                // $this->output()->writeln('======================================');
                $field->value = $new_value;
                $this->output()->writeln("Updating $entity_type with ID {$entity->id()}");
                $entity->save();
              }
            }
          }
        }

        $offset += $limit;
      } while (TRUE);
    }
    
    return static::EXIT_SUCCESS;
  }

  protected function changeLinkitFieldNameInMediaEmbeddings(string $value, bool &$changed): string {
    $dom = Html::load($value);
    $xpath = new \DOMXPath($dom);

    $changed = false;

    foreach ($xpath->query('//drupal-entity') as $element) {
        /** @var \DOMElement $element */
        $settings = $element->getAttribute('data-entity-embed-display-settings');
        if (empty($settings)) {
          continue;
        }
        $settings = Json::decode($settings, true);
        if (isset($settings['linkit'])) {
          $settings['linkit_uri'] = $settings['linkit'];
          unset($settings['linkit']);

          $settings = Json::encode($settings);

          $element->setAttribute('data-entity-embed-display-settings', $settings);

          $changed = true;
        }
    }

    return Html::serialize($dom);
  }
}
