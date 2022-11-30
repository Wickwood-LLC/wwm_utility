<?php

/**
 * @file
 * Tokens for the File (Field) Paths module.
 */

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;

// /**
//  * Implements hook_token_info().
//  */
// function bfbf_token_info() {
//   $info['tokens']['file']['ffp-name-only'] = [
//     'name'        => t("File name"),
//     'description' => t("File name without extension."),
//   ];

//   $info['tokens']['file']['ffp-name-only-original'] = [
//     'name'        => t("File name - original"),
//     'description' => t("File name without extension - original."),
//   ];

//   $info['tokens']['file']['ffp-extension-original'] = [
//     'name'        => t("File extension - original"),
//     'description' => t("File extension - original."),
//   ];

//   return $info;
// }

/**
 * Implements hook_token_info_alter()
 */
function wwm_utility_token_info_alter(&$data) {
  // Modify description of node tokens for our site.
  $data['tokens']['site']['global-metatag-image'] = [
    'name' => t("Global Metatag Image URL"),
    'description' => t("The URL to use for global metatag image."),
  ];
}

/**
 * Implements hook_tokens().
 */
function wwm_utility_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $url_options = ['absolute' => TRUE];
  if (isset($language)) {
    $url_options['language'] = $language;
  }

  $replacements = [];

  if ($type == 'site') {
    // $file_system = \Drupal::service('file_system');

    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'global-metatag-image':
          $image_media = Media::load(\Drupal::config('wwm_utility.settings')->get('global_metatag_image'));

          if ($image_media) {
            $fid = $image_media->getSource()->getSourceFieldValue($image_media);
            $file = File::load($fid);

            $replacements[$original] = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
          }
          break;
      }
    }
  }

  return $replacements;
}

/**
 * Gets the name of an image media item's source field.
 *
 * @param \Drupal\media\MediaInterface $media
 *   The media item being embedded.
 *
 * @return string|null
 *   The name of the image source field configured for the media item, or
 *   NULL if the source field is not an image field.
 */
// function bfbf_get_media_image_source_field_name(MediaInterface $media) {
//   $field_definition = $media->getSource()
//     ->getSourceFieldDefinition($media->bundle->entity);
//   $item_class = $field_definition->getItemDefinition()->getClass();
//   if (is_a($item_class, ImageItem::class, TRUE)) {
//     return $field_definition->getName();
//   }
//   return NULL;
// }
