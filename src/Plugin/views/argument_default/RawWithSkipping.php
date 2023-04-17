<?php

namespace Drupal\wwm_utility\Plugin\views\argument_default;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\argument_default\Raw;

/**
 * Default argument plugin to use the raw value from the URL.
 *
 * @ingroup views_argument_default_plugins
 *
 * @ViewsArgumentDefault(
 *   id = "raw_with_skipping",
 *   title = @Translation("Raw value from URL or skip")
 * )
 */
class RawWithSkipping extends Raw {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['skipping_value'] = ['default' => 'all'];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['skipping_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Skipping value'),
      '#default_value' => $this->options['skipping_value'],
      '#description' => $this->t('The value to be returned if user having any of selected roles above. Usually you don\'t need to change this. Make sure it matches with the Exception Value of this filter.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    $argument = parent::getArgument();
    if ($argument === NULL) {
      return $this->options['skipping_value'];
    }
    return $argument;
  }
}
