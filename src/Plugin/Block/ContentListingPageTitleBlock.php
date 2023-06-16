<?php

namespace Drupal\wwm_utility\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a block to display the page title.
 *
 * @Block(
 *   id = "wwm_content_listing_page_title",
 *   admin_label = @Translation("Content Listing Page title"),
 * )
 */
class ContentListingPageTitleBlock extends BlockBase {

  /**
   * The page title: a string (plain title) or a render array (formatted title).
   *
   * @var string|array
   */
  protected static $title = '';

  /**
   * {@inheritdoc}
   */
  public static function getTitle() {
    return static::$title;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'prefix' => '',
      'year_parameter_position' => 2,
      'month_parameter_position' => 3,
      'day_parameter_position' => 4,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix'),
      '#default_value' => $this->configuration['prefix'],
      '#required' => TRUE,
    ];

    $form['year_parameter_position'] = [
      '#type' => 'number',
      '#title' => $this->t('Year URL Parameter Position'),
      '#description' => $this->t('The numbering starts from 1, e.g. on the page admin/structure/types, the 3rd path component is "types"'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['year_parameter_position'],
      '#min' => 1,
      '#max' => 50,
      '#step' => 1,
      '#size' => 3,
    ];

    $form['month_parameter_position'] = [
      '#type' => 'number',
      '#title' => $this->t('Month URL Parameter Position'),
      '#description' => $this->t('The numbering starts from 1, e.g. on the page admin/structure/types, the 3rd path component is "types"'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['month_parameter_position'],
      '#min' => 1,
      '#max' => 50,
      '#step' => 1,
      '#size' => 3,
    ];

    $form['day_parameter_position'] = [
      '#type' => 'number',
      '#title' => $this->t('Day URL Parameter Position'),
      '#description' => $this->t('The numbering starts from 1, e.g. on the page admin/structure/types, the 3rd path component is "types"'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['day_parameter_position'],
      '#min' => 1,
      '#max' => 50,
      '#step' => 1,
      '#size' => 3,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['prefix'] = $form_state->getValue('prefix');
    $this->configuration['year_parameter_position'] = $form_state->getValue('year_parameter_position');
    $this->configuration['month_parameter_position'] = $form_state->getValue('month_parameter_position');
    $this->configuration['day_parameter_position'] = $form_state->getValue('day_parameter_position');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $path = \Drupal::request()->getpathInfo();
    $arg  = explode('/', trim($path, '/'));
    if (!empty($arg[$this->configuration['year_parameter_position'] - 1]) && !empty($arg[$this->configuration['month_parameter_position'] - 1]) && !empty($arg[$this->configuration['day_parameter_position'] - 1])) {
      $year = $arg[$this->configuration['year_parameter_position'] - 1];
      $month = static::monthNumberToName($arg[$this->configuration['month_parameter_position'] - 1]);
      $day = $arg[$this->configuration['day_parameter_position'] - 1];
      static::$title = $this->t('@prefix from @month @day, @year', ['@prefix' => $this->configuration['prefix'], '@year' => $year, '@month' => $month, '@day' => $day]);
    }
    else if (!empty($arg[$this->configuration['year_parameter_position'] - 1]) && !empty($arg[$this->configuration['month_parameter_position'] - 1])) {
      $year = $arg[$this->configuration['year_parameter_position'] - 1];
      $month = static::monthNumberToName($arg[$this->configuration['month_parameter_position'] - 1]);
      static::$title = $this->t('@prefix from @month @year', ['@prefix' => $this->configuration['prefix'], '@year' => $year, '@month' => $month]);
    }
    else if (!empty($arg[$this->configuration['year_parameter_position'] - 1])) {
      $year = $arg[$this->configuration['year_parameter_position'] - 1];
      static::$title = $this->t('@prefix from @year', ['@prefix' => $this->configuration['prefix'], '@year' => $year]);
    }

    return [];
  }

  public static function monthNumberToName($number) {
    $dateObj = \DateTime::createFromFormat('!m', (int) $number);
    return $dateObj->format('F');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // Need to always get correct title in wwm_utility_preprocess_page_title()
    return 0;
  }
}
