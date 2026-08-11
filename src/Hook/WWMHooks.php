<?php

namespace Drupal\wwm_utility\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for comment.
 */
class WWMHooks {

  use StringTranslationTrait;

  /**
   * Constructs a cron object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    protected ModuleHandlerInterface $moduleHandler
  ) {

  }

  /**
   * Implements hook_preprocess_HOOK() for field templates.
   */
  #[Hook('preprocess_field')]
  public function preprocessField(&$variables): void {
    // Make the iframe to take available full width.
    // The fitvids module will make embedded videos to fluid width by default.
    // However it will not work if the Klaro module is installed
    // The attached JS fixes that.
    if ($this->moduleHandler->moduleExists('fitvids') && $this->moduleHandler->moduleExists('klaro')) {
      if ($variables["element"]['#formatter'] == "oembed") {
        foreach ($variables['items'] as $i => $item) {
          $variables['items'][$i]['content']['#attached']['library'][] = 'wwm_utility/fitvidsjs';
        }
      }
    }
  }
}
