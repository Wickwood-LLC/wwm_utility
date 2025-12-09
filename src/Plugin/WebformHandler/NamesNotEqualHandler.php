<?php

namespace Drupal\wwm_utility\Plugin\WebformHandler;

use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Webform handler to reject identical first and last names.
 *
 * @WebformHandler(
 *   id = "names_not_equal",
 *   label = "Reject same first/last name",
 *   category = @Translation("Validation"),
 *   description = @Translation("Prevents submissions where first and last name are identical."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class NamesNotEqualHandler extends WebformHandlerBase
{

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
    {
        $data = $webform_submission->getData();
        $first_name = isset($data['first_name']) ? $data['first_name'] : NULL;
        $last_name = isset($data['last_name']) ? $data['last_name'] : NULL;

        if (!empty($first_name) && $first_name === $last_name) {
            $form_state->setErrorByName('first_name', $this->t('First name and last name cannot be identical.'));
            $form_state->setErrorByName('last_name', $this->t('First name and last name cannot be identical.'));
        }
    }
}
