<?php

namespace Drupal\wwm_utility\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


class FindAliasForm extends FormBase {


  /**
   * Constructs a new FindAliasForm.
   */
  public function __construct() {
    
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wwm_utility_file_alias_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // // Set the method.
    // $form_state->setMethod('GET');

    // // GET forms must not be cached, so that the page output responds without
    // // caching.
    // $form['#cache'] = [
    //     'max-age' => 0,
    // ];

    // // The after_build removes elements from GET parameters. See
    // // TestForm::afterBuild().
    // $form['#after_build'] = ['::afterBuild'];

    $system_path = $this->getRequest()->query->get('system_path');

    $form['system_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('System Path'),
      '#default_value' => $system_path,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Find'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Custom after build to remove elements from being submitted as GET variables.
   */
  // public function afterBuild(array $element, FormStateInterface $form_state) {
  //   // Remove the form_token, form_build_id and form_id from the GET parameters.
  //   unset($element['form_token']);
  //   unset($element['form_build_id']);
  //   unset($element['form_id']);
  //   unset($element['op']);

  //   return $element;
  // }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!preg_match('~^/~', $form_state->getValue('system_path'))) {
      $form_state->setErrorByName('system_path', 'Path should start with / character');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('wwm_utility.find_alias', [], [
      'query' => ['system_path' => trim($form_state->getValue('system_path'))],
    ]);
  }
}