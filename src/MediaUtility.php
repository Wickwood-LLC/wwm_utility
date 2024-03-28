<?php

namespace Drupal\wwm_utility;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Component\Serialization\Json;
use Drupal\image\Entity\ImageStyle;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Utility\Html;

class MediaUtility {

  protected $embed_view_modes;

  const MEDIA_MISSING_MESSAGE = '<strong class="missing-media-message" style="color: red;">Embedded Media that should go here was deleted on Drupal 7 site and therefore could not be migrated to this site. Below is original D7 code in case you find it useful to find a replacement.</strong>';

  use StringTranslationTrait;

  public function getD7ViewModesFound() {
    return $this->embed_view_modes;
  }

  public function imageStyleReplacements() {
    return [
      'scale_480x270' => [
        'image_style' => 'image_medium_16x9',
      ],
      'responsive_image_large_16x9' => [
        'image_style' => 'image_large_16x9',
      ],
      'scale_320x240' => [
        'image_style' => 'image_medium_4x3',
      ],
      'responsive_image_small_16x9' => [
        'image_style' => 'image_small_16x9',
      ],
      'responsive_image_medium_4x3' => [
        'image_style' => 'image_medium_4x3',
      ],
      'responsive_image_small_4x3' => [
        'image_style' => 'image_small_4x3',
      ],
      'polaroid_landscape_rotate_right' => [
        'image_style' => 'responsive_polaroid_landscape',
        'rotate' => 'right',
        'align' => 'right',
      ],
      'polaroid_landscape_rotate_left' => [
        'image_style' => 'responsive_polaroid_landscape',
        'rotate' => 'left',
        'align' => 'left',
      ],
      'scale_640x480' => [
        'image_style' => 'image_large_4x3',
      ],
      'responsive_image_large_4x3' => [
        'image_style' => 'image_large_4x3',
      ],
      'responsive_image_medium_16x9' => [
        'image_style' => 'image_medium_16x9',
      ],
      'scale_560x315' => [
        'image_style' => 'image_large_16x9',
      ],
      'scale_320x180' => [
        'image_style' => 'image_medium_16x9',
      ],
      'original_size_no_watermark_' => [
        // Use original size.
      ],
      'original' => [
        // Use original size.
      ],
      'original_no_loading_on_mobile' => [
        // Use original size.
      ],
      'scale_640x360' => [
        'image_style' => 'image_large_16x9',
      ],
      'teaser' => [
        'image_style' => 'image_medium_4x3',
      ],
    ];
  }

  /**
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   * @param array $fields
   *  Field machine names for formatted fields to look for embeds.
   */
  public function findD7MediaEmbedsInEntity(FieldableEntityInterface $entity, $fields) {
    $entity_type = $entity->getEntityType();
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_definition = $entity_type_manager->getDefinition($entity_type->id());

    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type->id());
    if ($entity_definition->hasKey('revision')) {
      $revisions = $entity_storage->getQuery()
        ->allRevisions()
        ->condition($entity_type->getKey('id'), $entity->id())
        ->sort($entity_type->getKey('revision'), 'DESC')
        ->execute();
    }
    else {
      $revisions = [$entity->id() => $entity->id()];
    }

    $pattern = '/\[\[\{.*?"type":"media".*?\}\]\]/s';
    $database = \Drupal::database();

    $media_embeds = [
      'id' => $entity->id(),
      'label' => $entity->label(),
      'edit_page' => $entity->toUrl('edit-form')->toString(),
      'embeds' => [],
    ];

    $media_storage = \Drupal::entityTypeManager()->getStorage('media');

    foreach ($revisions as $revision_id => $entity_id) {
      if ($entity_definition->hasKey('revision')) {
        $revision = $entity_storage->loadRevision($revision_id);
      }
      else {
        $revision = $entity;
      }
      foreach ($fields as $field_name) {
        if ($revision->{$field_name}->value) {
          preg_match_all($pattern, $revision->{$field_name}->value, $matches);
          foreach ($matches[0] as $match) {
            $embed_info = [];
            // $media_embeds['embeds'][$field_name]['code'] = $match;
            $embed_info['code'] = $match;
            $embed_info['new_code'] = $this->convertD7MediaEmbedToD9($match);
            $match = str_replace("[[", "", $match);
            $match = str_replace("]]", "", $match);
            $tag_info = Json::decode($match);

            $media = $media_storage->load($tag_info['fid']);
            if (!$media) {
              $result = $database->query("SELECT original_fid FROM {duplicate_files} WHERE fid = :fid", [':fid' => $tag_info['fid']])->fetchField();
              if ($result) {
                $media = $media_storage->load($result);
                $embed_info['messages'][] = $this->t('Original media with ID @id could not be found, but replacement media with ID @new_id identified.', ['@id' => $tag_info['fid'], '@new_id' => $result]);
              }
              else {
                $embed_info['messages'][] = $this->t('Media could not be found with ID: @id. Replacement could not be found either.', ['@id' => $tag_info['fid']]);
              }
            }
            if ($media) {
              $embed_info['messages'][] = $this->t('Media of type @type', ['@type' => $media->bundle()]);
              $embed_info['messages'][] = $this->t('Embedded with view mode "@mode"', ['@mode' => $tag_info['view_mode']]);

              $d7_view_mode = $tag_info['view_mode'];
              if ($media->bundle() == 'image') {
                $image_style_replacements = $this->imageStyleReplacements();

                if (!empty($image_style_replacements[$d7_view_mode])) {
                  $image_style_replacement = $image_style_replacements[$d7_view_mode];
                  $display = 'entity_reference:media_image_responsive';
                  $image_style = $image_style_replacement['image_style'];

                  $embed_info['messages'][] = $this->t('Image style replacement found for "@old" is "@new"', ['@old' => $d7_view_mode, '@new' => $image_style]);

                  if (!empty($image_style_replacement['align'])) {
                    $align = $image_style_replacement['align'];
                    $embed_info['messages'][] = $this->t('Set to align @align', ['@align' => $align]);
                  }

                  if (!empty($image_style_replacement['rotate'])) {
                    $rotate = $image_style_replacement['rotate'];
                    $embed_info['messages'][] = $this->t('Set to rotate @rotate', ['@rotate' => $rotate]);
                  }
                }
                elseif (isset($image_style_replacements[$d7_view_mode])) {
                  // It exist but no replacement image style specified,
                  // Which means to use original size.
                  $embed_info['messages'][] = $this->t('Original size');
                }
                else if ($d7_view_mode == 'default') {
                  $embed_info['messages'][] = $this->t('Original size');
                }
                else {
                  $embed_info['messages'][] = $this->t('Image style not handled "@name"', ['@name' => $d7_view_mode]);
                }
              }
              if (!empty($embed_options['link'])) {
                $embed_info['messages'][] = $this->t('It is linked');
              }
            }
            $media_embeds['embeds'][$revision_id][$field_name][] = $embed_info;
          }
          if (empty($media_embeds['embeds'][$revision_id][$field_name])) {
            unset($media_embeds['embeds'][$revision_id][$field_name]);
          }
        }
      }
    }

    return $media_embeds;
  }

  /**
   * @param string $entity_type
   * @param array $field_types
   */
  public function findD7MediaEmbeds($entity_type, array $field_types, $entity_id = NULL) {
    $this->embed_view_modes = [];

    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_definition = $entity_type_manager->getDefinition($entity_type);

    $wwm_field_utility = \Drupal::service('wwm_utility.field');
    $entity_storage = $entity_type_manager->getStorage($entity_type);

    $fields = $wwm_field_utility->findFilesOfType($field_types, $entity_type);

    $media_embeds = [];

    foreach ($fields as $entity_type => $bundles) {
      foreach ($bundles as $bundle => $formatted_fields) {
        $query = \Drupal::entityQuery($entity_type)
          ->condition($entity_definition->getKey('bundle'), $bundle);
        if ($entity_id) {
          $query->condition($entity_definition->getKey('id'), $entity_id);
        }
        $results = $query->execute();
        foreach ($results as $id) {
          $entity = $entity_storage->load($id);
          $media_embeds_on_node = $this->findD7MediaEmbedsInEntity($entity, $fields[$entity_type][$bundle]);
          if (!empty($media_embeds_on_node['embeds'])) {
            $media_embeds[$entity->id()] = $media_embeds_on_node;
          }
        }
      }
    }
    return $media_embeds;
  }

  public function convertD7MediaEmbedToD9($media_embed_code) {
    $original_embed_code = $media_embed_code;
    $media_embed_code = str_replace("[[", "", $media_embed_code);
    $media_embed_code = str_replace("]]", "", $media_embed_code);
    $tag_info = Json::decode($media_embed_code);

    $media_storage = \Drupal::entityTypeManager()->getStorage('media');
    $media = $media_storage->load($tag_info['fid']);
    if (!$media) {
      $database = \Drupal::database();
      $result = $database->query("SELECT original_fid FROM {duplicate_files} WHERE fid = :fid", [':fid' => $tag_info['fid']])->fetchField();
      if ($result) {
        $media = $media_storage->load($result);
      }
      else {
        return static::MEDIA_MISSING_MESSAGE . "\n" . $original_embed_code;
      }
    }

    if ($media->bundle() == 'video') {
      // We don't support videos now, sorry!
      return;
    }

    $fields = [];
    if (!empty($tag_info['field_detas'])) {
      $fields = end($tag_info['field_detas']);
    }
    elseif (isset($tag_info['fields'])){
      $fields = $tag_info['fields'];
    }

    if (!is_array($fields)) {
      $fields = [];
    }

    $dom = new \DOMDocument('1.0', 'utf-8');
    $element = $dom->createElement('drupal-entity');

    $embed_options = [];
    foreach ($fields as $key => $value) {
      if (preg_match('#^field_media_placement#', $key)) {
        $embed_options['placement'] = $value;
      }
      else if (preg_match('#^field_long_caption.*value\\]$#', $key)) {
        $embed_options['caption'] = $value;
      }
      else if (preg_match('#^field_file_image_alt_text.*value\\]$#', $key)) {
        $element->setAttribute('alt', $value);
      }
      else if (preg_match('#^field_image_link.*url\\]$#', $key)) {
        $embed_options['link'] = $value;
      }
      else if (preg_match('#^field_image_link.*target\\]$#', $key)) {
        $embed_options['link_target'] = $value;
      }
    }

    $element->setAttribute('data-entity-type', 'media');
    $element->setAttribute('data-entity-uuid', $media->uuid());
    $element->setAttribute('data-langcode', $media->language()->getId());
    $element->setAttribute('data-embed-button', 'media');

    $display_settings = [];
    $d7_view_mode = $tag_info['view_mode'];
    if (!in_array($d7_view_mode, $this->embed_view_modes)) {
      $this->embed_view_modes[] = $d7_view_mode;
    }

    if ($media->bundle() == 'image') {
      $image_style_replacements = $this->imageStyleReplacements();

      if (!empty($image_style_replacements[$d7_view_mode])) {
        $image_style_replacement = $image_style_replacements[$d7_view_mode];
        $display = 'entity_reference:media_image_responsive';
        $image_style = $image_style_replacement['image_style'];
        $display_settings['image_style'] = $image_style_replacement['image_style'];

        if (!empty($image_style_replacement['align'])) {
          $align = $image_style_replacement['align'];
        }

        if (!empty($image_style_replacement['rotate'])) {
          $rotate = $image_style_replacement['rotate'];
        }
      }
      elseif (isset($image_style_replacements[$d7_view_mode])) {
        // It exist but no replacement image style specified,
        // Which means to use original size.
        $display = 'entity_reference:media_image_responsive';
      }
      else if ($d7_view_mode == 'default') {
        $display = 'entity_reference:media_image_responsive';
      }
      else {
        throw new \Exception($this->t('Please handle image style for view mode @view_mode', ['@view_mode' => $d7_view_mode]));
      }
      if (!empty($embed_options['link'])) {
        $display_settings['linkit'] = $embed_options['link'];
      }
    }
    else if ($d7_view_mode == 'media_link') {
      $display = 'entity_reference:generic_media_link';
      // In D7, there appears to be no way to give separate title for link.
      // $display_settings['use_url_as_link_text'] = 1;
      // Following settings as per MHCC site. Please change it depending on requrement for different site.
      $display_settings['link_text_type'] = 'filename';
      $display_settings['display_file_type'] = TRUE;
      $display_settings['display_icon'] = TRUE;
      $display_settings['display_file_size'] = TRUE;
      $display_settings['icon_position'] = 'before';
    }

    $element->setAttribute('data-entity-embed-display', $display);

    $element->setAttribute('data-entity-embed-display-settings', Json::encode((object) $display_settings));

    if (empty($align) && !empty($embed_options['placement'])) {
      switch ($embed_options['placement']) {
        case 'mce-align-center':
          $align = 'center';
          break;
        case 'mce-align-left':
          $align = 'left';
          break;
        case 'mce-align-right':
          $align = 'right';
          break;
        case '_none':
          $align = '';
          break;
        case 'mce-responsive':
          // For vieo only
          $align = '';
          break;
      }
    }
    if (!empty($align)) {
      $element->setAttribute('data-align', $align);
    }
    if (!empty($rotate)) {
      $element->setAttribute('data-rotate', $rotate);
    }

    $dom->appendChild($element);

    return $dom->saveHTML();
  }

  /**
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   * @param array $fields
   *  Field machine names for formatted fields to look for embeds.
   */
  public function findMediaEmbedsInEntity(FieldableEntityInterface $entity, $fields) {
    $entity_type = $entity->getEntityType();
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_definition = $entity_type_manager->getDefinition($entity_type->id());

    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type->id());
    if ($entity_definition->hasKey('revision')) {
      $revisions = $entity_storage->getQuery()
        ->allRevisions()
        ->condition($entity_type->getKey('id'), $entity->id())
        ->sort($entity_type->getKey('revision'), 'DESC')
        ->execute();
    }
    else {
      $revisions = [$entity->id() => $entity->id()];
    }

    $media_embeds = [
      'id' => $entity->id(),
      'label' => $entity->label(),
      'edit_page' => $entity->toUrl('edit-form')->toString(),
      'embeds' => [],
    ];

    foreach ($revisions as $revision_id => $entity_id) {
      if ($entity_definition->hasKey('revision')) {
        $revision = $entity_storage->loadRevision($revision_id);
      }
      else {
        $revision = $entity;
      }
      foreach ($fields as $field_name) {
        if ($revision->{$field_name}->value) {
          $media_embeds['embeds'][$revision_id][$field_name] = $this->findEntityEmbedsInText($revision->{$field_name}->value, 'media');
        }
      }
    }

    return $media_embeds;
  }

  /**
   * Find embeded entity information.
   *
   * @param $text
   *  Text to search embeds for.
   * @param $langcode
   */
  public function findEntityEmbedsInText($text, $embed_entity_type = NULL) {
    // $result = new FilterProcessResult($text);
    $nodes = [];

    if (strpos($text, 'data-entity-type') !== FALSE && (strpos($text, 'data-entity-embed-display') !== FALSE || strpos($text, 'data-view-mode') !== FALSE)) {
      $dom = Html::load($text);
      $xpath = new \DOMXPath($dom);

      foreach ($xpath->query('//drupal-entity[@data-entity-type and (@data-entity-uuid or @data-entity-id) and (@data-entity-embed-display or @data-view-mode)]') as $node) {
        /** @var \DOMElement $node */
        $entity_type = $node->getAttribute('data-entity-type');
        if ($embed_entity_type && $entity_type != $embed_entity_type) {
          continue;
        }
        $nodes[] = $node;
      }
    }

    return $nodes;
  }

  /**
   * Find usage of image styles used for media embeds.
   *
   * @param FieldableEntityInterface $entity
   *  Entity object to search fro the embeds
   * @param array $fields
   *  List of field names
   * @param array $image_styles_to_find
   *  List of image style name to restrict searching to.
   * @return array
   */
  public function findImageStylesUsedForEmbeds(FieldableEntityInterface $entity, $fields, $image_styles_to_find = []) {
    $embed_info = $this->findMediaEmbedsInEntity($entity, $fields);

    $image_style_usage = [];
    foreach ($embed_info['embeds'] as $revision_id => $revision_embeds) {
      foreach ($revision_embeds as $field => $embeds) {
        foreach ($embeds as $embed_node) {
          /** @var \DOMNode $embed_node */

          $display_settings = $embed_node->attributes->getNamedItem('data-entity-embed-display-settings');
          if ($display_settings) {
            $display_settings = json_decode($display_settings->nodeValue, TRUE);
            if (isset($display_settings['image_style'])) {
              $image_style = $display_settings['image_style'];
              if (!empty($image_styles_to_find) && !in_array($image_style, $image_styles_to_find)) {
                // Image style is not in the list given to restrict looking for.
                continue;
              }
              if (isset($image_style_usage[$revision_id][$field][$image_style])) {
                $image_style_usage[$revision_id][$field][$image_style]++;
              }
              else {
                $image_style_usage[$revision_id][$field][$image_style] = 1;
              }
            }
          }
        }
      }
    }
    return $image_style_usage;
  }

  /**
   * @param string $entity_type
   * @param array $field_types
   */
  public function findImageStylesInMediaEmbeds($entity_type, array $field_types, $image_style = NULL) {
    $this->embed_view_modes = [];

    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_definition = $entity_type_manager->getDefinition($entity_type);

    $wwm_field_utility = \Drupal::service('wwm_utility.field');
    $entity_storage = $entity_type_manager->getStorage($entity_type);

    $fields = $wwm_field_utility->findFilesOfType($field_types, $entity_type);

    $image_style_usage = [];

    if ($image_style) {
      $image_styles = [$image_style];
    }
    else {
      $image_styles = [];
    }

    foreach ($fields as $entity_type => $bundles) {
      foreach ($bundles as $bundle => $formatted_fields) {
        $query = \Drupal::entityQuery($entity_type)
          ->condition($entity_definition->getKey('bundle'), $bundle);

        $results = $query->execute();
        foreach ($results as $id) {
          $entity = $entity_storage->load($id);
          $image_styles_in_embeds = $this->findImageStylesUsedForEmbeds($entity, $fields[$entity_type][$bundle], $image_styles);
          if (!empty($image_styles_in_embeds)) {
            $image_style_usage[$entity->id()] = $image_styles_in_embeds;
          }
        }
      }
    }
    return $image_style_usage;
  }
}
