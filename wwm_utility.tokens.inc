<?php

/**
 * @file
 * Tokens for WWM Utility module.
 */

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;

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
