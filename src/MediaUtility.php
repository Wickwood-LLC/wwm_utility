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
  public function findD7MediaEmbedsInEntity(FieldableEntityInterface $entity, $fields) {
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

  /**
   * @param string $entity_type
   * @param array $field_types
   */
  public function findD7MediaEmbeds($entity_type, array $field_types) {
    $wwm_field_utility = \Drupal::service('wwm_utility.field');
    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type);

    $fields = $wwm_field_utility->findFilesOfType($field_types, 'node');

    $media_embeds = [];

    foreach ($fields as $entity_type => $bundles) {
      foreach ($bundles as $bundle => $formatted_fields) {
        $query = \Drupal::entityQuery($entity_type)
          ->condition('type', $bundle);
        $results = $query->execute();
        foreach ($results as $nid) {
          $node = $entity_storage->load($nid);
          $media_embeds_on_node = $this->findD7MediaEmbedsInEntity($node, $fields[$entity_type][$bundle]);
          if (!empty($media_embeds_on_node['embeds'])) {
            $media_embeds[$node->id()] = $media_embeds_on_node;
          }
        }
      }
    }
    return $media_embeds;
  }
}
