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
    return ['prefix' => ''];
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

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['prefix'] = $form_state->getValue('prefix');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $path = \Drupal::request()->getpathInfo();
    $arg  = explode('/', trim($path, '/'));
    if (count($arg) == 4) {
      $year = $arg[1];
      $month = static::monthNumberToName($arg[2]);
      $day = $arg[3];
      static::$title = $this->t('@prefix from @month @day, @year', ['@prefix' => $this->configuration['prefix'], '@year' => $year, '@month' => $month, '@day' => $day]);
    }
    else if (count($arg) == 3) {
      $year = $arg[1];
      $month = static::monthNumberToName($arg[2]);
      static::$title = $this->t('@prefix from @month @year', ['@prefix' => $this->configuration['prefix'], '@year' => $year, '@month' => $month]);
    }
    else if (count($arg) == 2) {
      $year = $arg[1];
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
