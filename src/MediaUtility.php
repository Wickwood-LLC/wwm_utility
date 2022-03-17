<?php

namespace Drupal\wwm_utility;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Component\Serialization\Json;
use Drupal\image\Entity\ImageStyle;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class MediaUtility {

  use StringTranslationTrait;

  public function imageStyleReplacements() {
    return [
      'scale_480x270' => 'scale_landscape_480x270',
      'responsive_image_large_16x9' => 'image_large_16x9',
      'responsive_image_small_16x9' => 'image_small_16x9',
      'responsive_image_medium_4x3' => 'image_medium_4x3',
      'responsive_image_small_4x3' => 'image_small_4x3',
      'polaroid_landscape_rotate_right' => 'polaroid_landscape_290x210_rotate_left',
      'scale_640x480' => 'scale_landscape_640x480',
      'responsive_image_large_4x3' => 'image_large_4x3',
      'responsive_image_medium_16x9' => 'landscape_16x9_medium',
    ];
  }

  /**
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   * @param array $fields
   *  Field machine names for formatted fields to look for embeds.
   */
  public function findD7MediaEmbedsInEntity(FieldableEntityInterface $entity, $fields) {
    $pattern = '/\[\[\{.*?"type":"media".*?\}\]\]/s';

    $media_embeds = [
      'id' => $entity->id(),
      'label' => $entity->label(),
      'edit_page' => $entity->toUrl('edit-form')->toString(),
      'embeds' => [],
    ];

    $media_storage = \Drupal::entityTypeManager()->getStorage('media');

    foreach ($fields as $field_name) {
      if ($entity->{$field_name}->value) {
        preg_match_all($pattern, $entity->{$field_name}->value, $matches);
        foreach ($matches[0] as $match) {
          $embed_info = [];
          // $media_embeds['embeds'][$field_name]['code'] = $match;
          $embed_info['code'] = $match;
          $match = str_replace("[[", "", $match);
          $match = str_replace("]]", "", $match);
          $tag_info = Json::decode($match);

          $media = $media_storage->load($tag_info['fid']);
          $embed_info['messages'][] = $this->t('Media of type @type', ['@type' => $media->bundle()]);
          $embed_info['messages'][] = $this->t('Embedded with view mode "@mode"', ['@mode' => $tag_info['view_mode']]);

          if ($media->bundle() == 'image') {
            $d7_view_mode = $tag_info['view_mode'];
            if ($d7_view_mode == 'default') {
              $embed_info['messages'][] = $this->t('Original size');
            }
            else {
              $image_style = ResponsiveImageStyle::load($d7_view_mode);
              if ($image_style) {
                $embed_info['messages'][] = $this->t('It is responsive image style');
                $display = 'entity_reference:media_image_responsive';
              }
              else {
                $image_style = ImageStyle::load($d7_view_mode);
                if ($image_style) {
                  $embed_info['messages'][] = $this->t('It is regular image style');
                  $display = 'entity_reference:static_image';
                }
              }
              if ($image_style) {
                $display_settings['image_style'] = $d7_view_mode;
                if (!empty($embed_options['link'])) {
                  $display_settings['linkit'] = $embed_options['link'];
                }
              }
              else {
                $image_style_replacements = $this->imageStyleReplacements();
                if (isset($image_style_replacements[$d7_view_mode])) {
                  if (preg_match('#^responsive_#', $d7_view_mode)) {
                    $display = 'entity_reference:media_image_responsive';
                  }
                  else {
                    $display = 'entity_reference:static_image';
                  }
                  $display_settings['image_style'] = $image_style_replacements[$d7_view_mode];
                  $embed_info['messages'][] = $this->t('Image style replacement found for "@old" is "@new"', ['@old' => $d7_view_mode, '@new' => $image_style_replacements[$d7_view_mode]]);
                }
                else {
                  $embed_info['messages'][] = $this->t('No image style "@name" found', ['@name' => $d7_view_mode]);
                }
              }
            }
      
          }
          if (!empty($embed_options['link'])) {
            $embed_info['messages'][] = $this->t('It is linked');
          }
          $media_embeds['embeds'][$field_name][] = $embed_info;
        }
        if (empty($media_embeds['embeds'][$field_name])) {
          unset($media_embeds['embeds'][$field_name]);
        }
      }
    }
    return $media_embeds;
  }

  /**
   * @param string $entity_type
   * @param array $field_types
   */
  public function findD7MediaEmbeds($entity_type, array $field_types) {
    $wwm_field_utility = \Drupal::service('wwm_utility.field');
    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type);

    $fields = $wwm_field_utility->findFilesOfType($field_types, $entity_type);

    $media_embeds = [];

    foreach ($fields as $entity_type => $bundles) {
      foreach ($bundles as $bundle => $formatted_fields) {
        $query = \Drupal::entityQuery($entity_type)
          ->condition('type', $bundle);
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
    $media_embed_code = str_replace("[[", "", $media_embed_code);
    $media_embed_code = str_replace("]]", "", $media_embed_code);
    $tag_info = Json::decode($media_embed_code);

    $media_storage = \Drupal::entityTypeManager()->getStorage('media');
    $media = $media_storage->load($tag_info['fid']);

    if (!empty($tag_info['field_detas'])) {
      $fields = end($tag_info['field_detas']);
    }
    else {
      $fields = $tag_info['fields'];
    }

    $embed_options = [];
    foreach ($fields as $key => $value) {
      if (preg_match('#^field_media_placement#', $key)) {
        $embed_options['placement'] = $value;
      }
      else if (preg_match('#^field_long_caption.*value\\]$#', $key)) {
        $embed_options['caption'] = $value;
      }
      else if (preg_match('#^field_long_caption.*value\\]$#', $key)) {
        $embed_options['caption'] = $value;
      }
      else if (preg_match('#^field_image_link.*url\\]$#', $key)) {
        $embed_options['link'] = $value;
      }
      else if (preg_match('#^field_image_link.*target\\]$#', $key)) {
        $embed_options['link_target'] = $value;
      }
    }

    $dom = new \DOMDocument('1.0', 'utf-8');
    $element = $dom->createElement('drupal-entity');

    $element->setAttribute('data-entity-type', 'media');
    $element->setAttribute('data-entity-uuid', $media->uuid());
    $element->setAttribute('data-langcode', $media->language()->getId());
    $element->setAttribute('data-embed-button', 'media');

    $display_settings = [];

    if ($media->bundle() == 'image') {
      $d7_view_mode = $tag_info['view_mode'];
      if ($d7_view_mode == 'default') {
        $image_style = '';
        $display = 'entity_reference:static_image';
      }
      else {
        $image_style = ResponsiveImageStyle::load($d7_view_mode);
        if ($image_style) {
          $display = 'entity_reference:media_image_responsive';
        }
        else {
          $image_style = ImageStyle::load($d7_view_mode);
          if ($image_style) {
            $display = 'entity_reference:static_image';
          }
        }
        if ($image_style) {
          $display_settings['image_style'] = $d7_view_mode;
        }
      }
      if (!empty($embed_options['link'])) {
        $display_settings['linkit'] = $embed_options['link'];
      }
    }

    $element->setAttribute('data-entity-embed-display-settings', Json::encode((object) $display_settings));

    if (!empty($embed_options['placement'])) {
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
      if (!empty($align)) {
        $element->setAttribute('data-align', $align);
      }
    }

    $dom->appendChild($element);

    return $dom->saveHTML();
  }
}
