<?php

namespace Drupal\wwm_utility\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\Entity\Media;

/**
 * Media Extra settings.
 */
class SettingsForm extends ConfigFormBase {
  /** @var string Config settings */
  const SETTINGS = 'wwm_utility.settings';


  public function getFormId() {
    return 'wwm_utility_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['global_metatag_image'] = [

      '#type' => 'entity_autocomplete',
      '#title' => t('Global Metatag Image'),
      '#target_type' => 'media',
      '#selection_settings' => [
        'include_anonymous' => FALSE,
      ],
      '#default_value' => Media::load($config->get('global_metatag_image')),
      // Validation is done in static::validateConfigurationForm().
      '#validate_reference' => FALSE,
      '#size' => '6',
      '#maxlength' => '60',
      '#description' => '',
    ];


    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration
    $this->configFactory->getEditable(static::SETTINGS)
      // Set the submitted editor CSS setting
      ->set('global_metatag_image', $form_state->getValue('global_metatag_image'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}