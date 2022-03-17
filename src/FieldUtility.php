<?php

namespace Drupal\wwm_utility;

class FieldUtility {

  public function findFilesOfType($field_types, $entity_type, $bundles = NULL) {
    if (!is_array($field_types)) {
      $field_types = [$field_types];
    }

    if ($bundles) {
      if (!is_array($bundles)) {
        $bundles = [$bundles];
      }
    }
    else {
      $bundles = array_keys(\Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type));
    }
    
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $field_type_plugin_manager = \Drupal::service('plugin.manager.field.field_type');
    
    $fields_found = [];
    
    foreach ($bundles as $bundle_name) {
      $fields = $entityFieldManager->getFieldDefinitions($entity_type, $bundle_name);
      foreach ($fields as $field_definition) {
        $field_type = $field_definition->getType();
        if (in_array($field_type, $field_types)) {
          $fields_found[$entity_type][$bundle_name][] = $field_definition->getName();
        }
      }
    }
    return $fields_found;
  }
}