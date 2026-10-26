<?php

namespace Drupal\wwm_utility\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides domain selection settings.
 */
class DomainSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'wwm_utility_domain_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['wwm_utility.domain_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
  ): array {
    $config = $this->config('wwm_utility.domain_settings');

    $form['maintenance_mode'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enable Maintenance Mode for Domains'),
      '#description' => $this->t(
        'Select the domains to enable maintenance mode for.'
      ),
      '#options' => [],
      '#default_value' => $config->get('maintenance_mode') ?? [],
    ];

    $domains = \Drupal::entityTypeManager()
      ->getStorage('domain')
      ->loadMultipleSorted();

    foreach ($domains as $domain) {
      $form['maintenance_mode']['#options'][$domain->id()] =
        $domain->label() . ' (' . $domain->getHostname() . ')';
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $values = $form_state->getValue('maintenance_mode');

    // Remove unchecked entries, which have a value of 0.
    $selected_domains = array_keys(array_filter($values));

    $this->configFactory()
      ->getEditable('wwm_utility.domain_settings')
      ->set('maintenance_mode', $selected_domains)
      ->save();

    parent::submitForm($form, $form_state);
  }

  public static function access(AccountInterface $account) {
    if ((\Drupal::moduleHandler()->moduleExists('domain')) && $account->hasPermission('administer site configuration') ) {
        return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
