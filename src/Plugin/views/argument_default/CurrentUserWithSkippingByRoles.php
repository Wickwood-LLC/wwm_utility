<?php

namespace Drupal\wwm_utility\Plugin\views\argument_default;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Default argument plugin to extract the current user but allowing to skip if user has any
 * of selected roles.
 *
 * @ViewsArgumentDefault(
 *   id = "current_user_with_skipping_by_roles",
 *   title = @Translation("User ID from logged in user with skipping for selected roles")
 * )
 */
class CurrentUserWithSkippingByRoles extends ArgumentDefaultPluginBase implements CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['roles'] = ['default' => []];
    $options['skipping_value'] = ['default' => 'all'];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select roles for which this filter will not be applied'),
      '#default_value' => $this->options['roles'],
      '#options' => array_map('\Drupal\Component\Utility\Html::escape', user_role_names()),
      '#description' => $this->t('Filtering to current user ID will be skipped if current user having any of selected roles here. On skipping, it will return skipping value configured below.'),
    ];

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
    $roles = array_filter($this->options['roles']);

    if (!empty($roles) && !empty(array_intersect($roles, \Drupal::currentUser()->getRoles()))) {
      return $this->options['skipping_value'];
    }
    else {
      return \Drupal::currentUser()->id();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['user'];
  }

}
