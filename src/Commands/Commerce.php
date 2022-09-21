<?php

namespace Drupal\wwm_utility\Commands;

use Drush\Commands\DrushCommands;
// use Drupal\wwm_utility\MediaUtility;

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
class Commerce extends DrushCommands {

  protected $d9_database;

  protected $d7_database;

  /**
   */
  public function __construct() {
    $this->d7_database = \Drupal\Core\Database\Database::getConnection('default', 'migrate');

    // Ensure connection to default database.
    \Drupal\Core\Database\Database::setActiveConnection();
    $this->d9_database = \Drupal::database();
  }

  public function __destruct() {
    // Reset connection to default database.
    \Drupal\Core\Database\Database::setActiveConnection();
  }

  /**
   * Find and remove duplicate
   * 
   * @command wwm:find-and-remove-duplicate-user-profiles
   * 
   * @param int $profile_type
   *  Profile type to be used.
   */
  public function findAndRemoveDuplicateUserProfiles($profile_type) {

    $entity_type_manager = \Drupal::entityTypeManager();
    // $entity_definition = $entity_type_manager->getDefinition($entity_type);
    $user_storage = $entity_type_manager->getStorage('user');
    $profile_storage = $entity_type_manager->getStorage('profile');

    $user_ids = \Drupal::entityQuery('user')
      ->accessCheck(FALSE)
      ->execute();

    foreach ($user_ids as $user_id) {
      $user = $user_storage->load($user_id);

      $profile_ids = \Drupal::entityQuery('profile')
        ->condition('type', $profile_type)
        ->condition('uid', $user->id())
        // ->sort('created', 'DESC')
        ->accessCheck(FALSE)
        ->execute();
      $unique_profiles = [];
      $duplicate_profiles = [];
      foreach ($profile_ids as $profile_id) {
        $unique = TRUE;
        $profile = $profile_storage->load($profile_id);
        foreach ($unique_profiles as $unique_profile) {
          $same_country_code = $profile->address->country_code == $unique_profile->address->country_code;
          $same_administrative_area = $profile->address->administrative_area == $unique_profile->address->administrative_area;
          $same_locality = $profile->address->locality == $unique_profile->address->locality;
          $same_dependent_locality = $profile->address->dependent_locality == $unique_profile->address->dependent_locality;
          $same_postal_code = $profile->address->postal_code == $unique_profile->address->postal_code;
          $same_sorting_code = $profile->address->sorting_code == $unique_profile->address->sorting_code;
          $same_address_line1 = $profile->address->address_line1 == $unique_profile->address->address_line1;
          $same_address_line2 = $profile->address->address_line2 == $unique_profile->address->address_line2;
          $same_organization = $profile->address->organization == $unique_profile->address->organization;
          $same_given_name = $profile->address->given_name == $unique_profile->address->given_name;
          $same_additional_name = $profile->address->additional_name == $unique_profile->address->additional_name;
          $same_family_name = $profile->address->family_name == $unique_profile->address->family_name;

          if ($same_country_code && $same_administrative_area && $same_locality && $same_dependent_locality && $same_postal_code && $same_sorting_code
            && $same_address_line1 && $same_address_line2 && $same_organization && $same_given_name && $same_additional_name && $same_family_name
          ) {
            $unique = FALSE;
            break;
          }
        }
        if ($unique) {
          $unique_profiles[$profile_id] = $profile;
        }
        else {
          $duplicate_profiles[$profile_id] = $profile;
        }
      }
      if (!empty($duplicate_profiles)) {
        foreach ($duplicate_profiles as $profile) {
          $this->logger()->notice(dt('Deleting duplicate profile @pid...', ['@pid' => $profile->id()]));
          $profile->delete();
        }
      }
    }

  }
}
