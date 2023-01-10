<?php

namespace Drupal\wwm_utility\Plugin\Commerce\CheckoutFlow;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\MultistepDefault;

/**
 * Provides the default multistep checkout flow.
 *
 * @CommerceCheckoutFlow(
 *   id = "multistep_wwm",
 *   label = "Multistep - WWM",
 * )
 */
class MultistepWWM extends MultistepDefault {

  /**
   * {@inheritdoc}
   */
  public function getSteps() {
    $original_steps = parent::getSteps();
    // We want to inspert this step.
    $new_steps =  [
      'update' => [
        'label' => $this->t('Update'),
        'has_sidebar' => TRUE,
        'previous_label' => $this->t('Go back'),
        'next_label' => $this->t('Continue'),
      ],
    ];

    return array_slice($original_steps, 0, 1, true) +
      $new_steps +
      array_slice($original_steps, 1, count($original_steps)-1, true);
  }

}
