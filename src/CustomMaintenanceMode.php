<?php

namespace Drupal\wwm_utility;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Site\MaintenanceModeInterface;

class CustomMaintenanceMode implements MaintenanceModeInterface {

  public function __construct(
    protected MaintenanceModeInterface $inner,
  ) {}

  public function applies(RouteMatchInterface $route_match) {
    // Your custom business logic.
    $custom_maintenance = $this->shouldEnableMaintenance();

    return $custom_maintenance || $this->inner->applies($route_match);
  }

  protected function shouldEnableMaintenance(): bool {
    if (!\Drupal::moduleHandler()->moduleExists('domain')) {
      return FALSE;
    }

    $config = \Drupal::config('wwm_utility.domain_settings');
    $domains = $config->get('maintenance_mode') ?? [];
    $current_domain = \Drupal::service('domain.negotiator')->getActiveDomain()->id();
    return in_array($current_domain, $domains);
  }

  public function exempt(\Drupal\Core\Session\AccountInterface $account) {
    return $this->inner->exempt($account);
  }

  public function getSiteMaintenanceMessage() {
    return $this->inner->getSiteMaintenanceMessage();
  }

}