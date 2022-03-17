<?php

namespace Drupal\wwm_utility;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Component\Serialization\Json;

class MediaUtility {

  /**
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   * @param array $fields
   *  Field machine names for formatted fields to look for embeds.
   */
  public function findD7MediaEmbeds(FieldableEntityInterface $entity, $fields) {
    $pattern = '/\[\[\{.*?"type":"media".*?\}\]\]/s';

    $media_embeds = [
      'id' => $entity->id(),
      'label' => $entity->label(),
      'edit_page' => $entity->toUrl('edit-form')->toString(),
      'embeds' => [],
    ];
    foreach ($fields as $field_name) {
      if ($entity->{$field_name}->value) {
        preg_match_all($pattern, $entity->{$field_name}->value, $matches);
        foreach ($matches[0] as $match) {
          $media_embeds['embeds'][$field_name][] = $match;
          $match = str_replace("[[", "", $match);
          $match = str_replace("]]", "", $match);
          $tag_info = Json::decode($match);
        }
        if (empty($media_embeds['embeds'][$field_name])) {
          unset($media_embeds['embeds'][$field_name]);
        }
      }
    }
    return $media_embeds;
  }
}
