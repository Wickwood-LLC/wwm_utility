<?php

namespace Drupal\wwm_utility\Plugin\WebformHandler;

use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Webform handler to reject submission if two field values are the same.
 *
 * @WebformHandler(
 *   id = "wwm_utility_fields_not_equal",
 *   label = "Fields values are not equal",
 *   category = @Translation("Validation"),
 *   description = @Translation("Prevents submissions when two fields having equal values."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class FieldsNotEqualHandler extends WebformHandlerBase
{
    public function defaultConfiguration()
    {
        return parent::defaultConfiguration() + [
            'field_1_name' => '',
            'field_2_name' => '',
            'error_message' => 'Field 1 and Field 2 cannot be the same',
        ];
    }

    public function getSummary()
    {
        return parent::getSummary();
    }

    public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
        $form['field_1_name'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Field 1 Name'),
            '#default_value' => $this->configuration['field_1_name'],
            '#required' => TRUE,
        ];

        $form['field_2_name'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Field 2 Name'),
            '#default_value' => $this->configuration['field_2_name'],
            '#required' => TRUE,
        ];

        $form['error_message'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Error Message'),
            '#default_value' => $this->configuration['error_message'],
            '#required' => TRUE,
        ];
        return $this->setSettingsParents($form);
    }

    public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
        parent::submitConfigurationForm($form, $form_state);
        $this->configuration['field_1_name'] = $form_state->getValue('field_1_name');
        $this->configuration['field_2_name'] = $form_state->getValue('field_2_name');
        $this->configuration['error_message'] = $form_state->getValue('error_message');
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
    {
        $data = $webform_submission->getData();
        $field_1_name = $this->configuration['field_1_name'];
        $field_2_name = $this->configuration['field_2_name'];
        $first_name = isset($data[$field_1_name]) ? $data[$field_1_name] : NULL;
        $last_name = isset($data[$field_2_name]) ? $data[$field_2_name] : NULL;

        if (!empty($first_name) && $first_name === $last_name) {
            $error_message = $this->configuration['error_message'];
            $form_state->setErrorByName($field_1_name, $error_message);
            $form_state->setErrorByName($field_2_name, $error_message);
        }
    }
}
