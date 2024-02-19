<?php

namespace Drupal\wwm_utility\Plugin\Commerce\CheckoutPane;

use Drupal\user\Entity\User;
use Drupal\field_group\FormatterHelper;
use Drupal\commerce\CredentialsCheckFloodInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\user\UserAuthInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\field_layout\Display\EntityDisplayWithLayoutInterface;
use Drupal\field_layout\FieldLayoutBuilder;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;

/**
 * Provides the login pane.
 *
 * @CommerceCheckoutPane(
 *   id = "wwm_user_profile",
 *   label = @Translation("Update User Profile"),
 *   default_step = "order_information",
 * )
 */
class UserProfile extends CheckoutPaneBase implements CheckoutPaneInterface, ContainerFactoryPluginInterface {

  /**
   * The credentials check flood controller.
   *
   * @var \Drupal\commerce\CredentialsCheckFloodInterface
   */
  protected $credentialsCheckFlood;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The user authentication object.
   *
   * @var \Drupal\user\UserAuthInterface
   */
  protected $userAuth;

  /**
   * The client IP address.
   *
   * @var string
   */
  protected $clientIp;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new Login object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface $checkout_flow
   *   The parent checkout flow.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce\CredentialsCheckFloodInterface $credentials_check_flood
   *   The credentials check flood controller.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\user\UserAuthInterface $user_auth
   *   The user authentication object.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow, EntityTypeManagerInterface $entity_type_manager, CredentialsCheckFloodInterface $credentials_check_flood, AccountInterface $current_user, UserAuthInterface $user_auth, RequestStack $request_stack, LanguageManagerInterface $language_manager = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $checkout_flow, $entity_type_manager);

    $this->credentialsCheckFlood = $credentials_check_flood;
    $this->currentUser = $current_user;
    $this->userAuth = $user_auth;
    $this->clientIp = $request_stack->getCurrentRequest()->getClientIp();
    if (!$language_manager) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $language_manager argument is deprecated in commerce:8.x-2.25 and is removed from commerce:3.x.');
      $language_manager = \Drupal::languageManager();
    }
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $checkout_flow,
      $container->get('entity_type.manager'),
      $container->get('commerce.credentials_check_flood'),
      $container->get('current_user'),
      $container->get('user.auth'),
      $container->get('request_stack'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'user_form_mode' => 'default',
      'pane_title' => 'Update User Profile',
      'show_condition' => 'all',
      'new_user_age' => '-1 hour',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    $summary = $this->t('Form mode: ') . $this->configuration['user_form_mode'] . '<br>';
    $summary .= $this->t('Pane title: ') . $this->configuration['pane_title'] . '<br>';
    $summary .= $this->t('Show for: ') . $this->configuration['show_condition'] . '<br>';

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form_modes = \Drupal::service('entity_display.repository')
      ->getFormModes('user');
    $form_mode_options = [];
    foreach ($form_modes as $form_mode_id => $form_mode) {
      if ($form_mode_id != 'register') {
        $form_mode_options[$form_mode_id] = $form_mode['label'];
      }
    }
    $form['user_form_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Form Mode for User Profile Edit'),
      '#default_value' => $this->configuration['user_form_mode'],
      '#options' => $form_mode_options,
      '#description' => $this->t('Select a user form mode to be used for the profile edit form.'),
    ];

    $form['pane_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pane Title'),
      '#default_value' => $this->configuration['pane_title'],
      '#description' => $this->t('Specify a title for the profile edit form.'),
    ];

    $form['show_condition'] = [
      '#type' => 'radios',
      '#title' => $this->t('Show for'),
      '#default_value' => $this->configuration['show_condition'],
      '#options' => [
        'all' => $this->t('All users'),
        'new_users' => $this->t('New users'),
      ],
    ];

    $form['new_user_age'] = [
      '#type' => 'textfield',
      '#title' => $this->t('New User Since'),
      '#default_value' => $this->configuration['new_user_age'],
      '#description' => $this->t('Enter relative time like "-1 hour" to determine how a new user is to be determined. Please see PHP documentation at <a href="https://www.php.net/manual/en/function.strtotime.php">https://www.php.net/manual/en/function.strtotime.php</a>'),
      '#states' => [
        'visible' => [
          ':input[name="configuration[panes][wwm_user_profile][configuration][show_condition]"]' => ['value' => 'new_users'],
        ],
      ],
    ];

    return $form;
  }

  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);

    $parents = $form['#parents'];
    if ($values['show_condition'] == 'new_users') {
      if (!strtotime($values['new_user_age'])) {
        $form_state->setError($form['new_user_age'], $this->t('Please enter a valid value.'));
      }
      else {
        if (strtotime($values['new_user_age']) >= time()) {
          $form_state->setError($form['new_user_age'], $this->t('Please enter a value will give a past time.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['user_form_mode'] = $values['user_form_mode'];
      $this->configuration['pane_title'] = $values['pane_title'];
      $this->configuration['show_condition'] = $values['show_condition'];
      $this->configuration['new_user_age'] = $values['new_user_age'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    if (!$this->currentUser->isAnonymous()) {
      if ($this->configuration['show_condition'] == 'new_users') {
        /** @var \Drupal\user\UserInterface $account */
        $account = User::load(\Drupal::currentUser()->id());
        if ($account->getCreatedTime() >= strtotime($this->configuration['new_user_age'])) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    // $pane_form['#attached']['library'][] = 'commerce_checkout/login_pane';

    $pane_form['user_profile'] = [
      '#type' => 'fieldset',
      '#title' => $this->configuration['pane_title'],
    ];

    $pane_form['user_profile']['update'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#op' => 'update',
      '#weight' => 50,
    ];

    /** @var \Drupal\user\UserInterface $account */
    $account = User::load(\Drupal::currentUser()->id());
    $form_display = EntityFormDisplay::collectRenderDisplay($account, $this->configuration['user_form_mode']);
    $form_display->buildForm($account, $pane_form['user_profile'], $form_state);

    // if ($form_display instanceof EntityDisplayWithLayoutInterface) {
    //   \Drupal::classResolver(FieldLayoutBuilder::class)->buildForm($pane_form['register'], $form_display);
    // }

    if (\Drupal::moduleHandler()->moduleExists('field_group')) {
      $entity = $account;

      $context = [
        'entity_type' => $entity->getEntityTypeId(),
        'bundle' => $entity->bundle(),
        'entity' => $entity,
        'context' => 'form',
        'display_context' => 'form',
        'mode' => $form_display->getMode(),
      ];

      field_group_attach_groups($pane_form['user_profile'], $context);
      $pane_form['user_profile']['#process'][] = [FormatterHelper::class, 'formProcess'];
      $pane_form['user_profile']['#pre_render'][] = [FormatterHelper::class, 'formGroupPreRender'];
    }

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    $triggering_element = $form_state->getTriggeringElement();
    $trigger = !empty($triggering_element['#op']) ? $triggering_element['#op'] : 'update';
    switch ($trigger) {
      case 'update':
      //   return;

      // case 'register':
        // $email = $values['user_profile']['mail'];
        // $username = $values['user_profile']['name'];
        // $password = trim($values['register']['password']);
        // if (empty($email)) {
        //   $form_state->setError($pane_form['register']['mail'], $this->t('Email field is required.'));
        //   return;
        // }
        // if (empty($username)) {
        //   $form_state->setError($pane_form['register']['name'], $this->t('Username field is required.'));
        //   return;
        // }
        // if (empty($password)) {
        //   $form_state->setError($pane_form['register']['password'], $this->t('Password field is required.'));
        //   return;
        // }

        /** @var \Drupal\user\UserInterface $account */
        $account = User::load(\Drupal::currentUser()->id());

        $form_display = EntityFormDisplay::collectRenderDisplay($account, $this->configuration['user_form_mode']);
        $form_display->extractFormValues($account, $pane_form['user_profile'], $form_state);
        $form_display->validateFormValues($account, $pane_form['user_profile'], $form_state);

        // // Manually flag violations of fields not handled by the form display.
        // // This is necessary as entity form displays only flag violations for
        // // fields contained in the display.
        // // @see \Drupal\user\AccountForm::flagViolations
        // $violations = $account->validate();
        // foreach ($violations->getByFields(['name', 'pass', 'mail']) as $violation) {
        //   [$field_name] = explode('.', $violation->getPropertyPath(), 2);
        //   $form_state->setError($pane_form['register'][$field_name], $violation->getMessage());
        // }

        if (!$form_state->hasAnyErrors()) {
          $account->save();
          // $form_state->set('logged_in_uid', $account->id());
          // if ($this->configuration['allow_registration'] && !empty($this->configuration['new_registration_mail'])) {
          //   _user_mail_notify($this->configuration['new_registration_mail'], $account);
          // }
        }
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $triggering_element = $form_state->getTriggeringElement();
    $trigger = !empty($triggering_element['#op']) ? $triggering_element['#op'] : 'continue';
    switch ($trigger) {
      case 'continue':
      //   break;

      // case 'login':
      // case 'register':
        $storage = $this->entityTypeManager->getStorage('user');
        /** @var \Drupal\user\UserInterface $account */
        // $account = $storage->load($form_state->get('logged_in_uid'));
        $account = User::load(\Drupal::currentUser()->id());

        // user_login_finalize($account);
        $this->order->setCustomer($account);
        // $this->credentialsCheckFlood->clearAccount($this->clientIp, $account->getAccountName());
        break;
    }

    $next_step = $this->checkoutFlow->getNextStepId($this->getStepId());

    $form_state->setRedirect('commerce_checkout.form', [
      'commerce_order' => $this->order->id(),
      'step' => $this->checkoutFlow->getNextStepId($this->getStepId()),
    ]);
  }

  /**
   * Checks whether guests can register after checkout is complete.
   *
   * @return bool
   *   TRUE if guests can register after checkout is complete, FALSE otherwise.
   */
  // protected function canRegisterAfterCheckout() {
  //   $completion_register_pane = $this->checkoutFlow->getPane('completion_register');
  //   return $completion_register_pane->getStepId() != '_disabled';
  // }

}
