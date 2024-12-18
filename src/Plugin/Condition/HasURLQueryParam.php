<?php

namespace Drupal\wwm_utility\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\StringTranslation\TranslatableMarkup;


/**
 * Provides the 'Has URL Query Param' condition.
 */
#[Condition(
  id: "wwmdgr_has_url_query_param",
  label: new TranslatableMarkup("Has URL Query Param(s)"),
  category:  new TranslatableMarkup("WWM"),
)]
class HasURLQueryParam extends ConditionPluginBase {
  const REG_EXP_PATTERN = '/^([A-Za-z0-9_-]+)(\=(\w+))?$/';
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'params' => '',
      'or_condition' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['params'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Query Parameters'),
      '#description' => $this->t('Add one ore more query param with optional values to evaulate.'),
      '#default_value' => $this->configuration['params'],
    ];

    $form['or_condition'] = [
      '#type' => 'checkbox',
      '#title' => 'Only one paramter condition need to be met (OR condition)',
      '#default_value' => $this->configuration['or_condition'],
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $error_lines = [];
    if (!empty($form_state->getValue('params'))) {
      $lines = preg_split("/\r\n|\n|\r/", $form_state->getValue('params'));
      foreach ($lines as $line => $line_string) {
        $match = NULL;
        if (!preg_match(static::REG_EXP_PATTERN, trim($line_string), $match)) {
          $error_lines[] = $line + 1;
        }
      }
    }
    if (!empty($error_lines)) {
      $plugin_def = $this->getPluginDefinition();
      $form_state->setErrorByName('params', $this->t('Params settings of %plugin got error on line(s) with incorrect values: @lines', ['%plugin' => $plugin_def['label'], '@lines' => implode(', ', $error_lines)]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['params'] = $form_state->getValue('params');
    $this->configuration['or_condition'] = $form_state->getValue('or_condition');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    $param_settings = $this->getParamSettings();
    if (!empty($param_settings)) {
      $param_representaitons = [];
      foreach ($param_settings as $param => $value) {
        if (empty($value)) {
          $param_representaitons[] = $param;
        }
        else {
          $param_representaitons[] = "$param=$value";
        }
      }
      $condition = $this->configuration['or_condition'] ? $this->t('any') : $this->t('all');
      return t('When page URL has @condition query param: @params.', ['@condition' => $condition, '@params' => implode(', ', $param_representaitons)]);
    }
    else {
      return t('Not cofigured.');
    }
  }

  public function getParamSettings() {
    $params = [];
    if (!empty($this->configuration['params'])) {
      $lines = preg_split("/\r\n|\n|\r/", $this->configuration['params']);
      foreach ($lines as $line) {
        $match = NULL;
        preg_match(static::REG_EXP_PATTERN, $line, $match);
        $params[$match[1]] = $match[3] ?? NULL;
      }
    }
    return $params;
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    \Drupal::service('page_cache_kill_switch')->trigger();
    $param_settings = $this->getParamSettings();
    if (!empty($this->configuration['params'])) {
      $url_query = \Drupal::request()->query;
      $return = !$this->configuration['or_condition'];
      foreach ($param_settings as $param => $value) {
        $match = NULL;
        if ($value === NULL) {
          $match = $url_query->has($param);
        }
        else {
          if ($url_query->has($param)) {
            $match = $url_query->get($param) == $value;
          }
          else {
            $match = FALSE;
          }
        }
        if ($this->configuration['or_condition']) {
          $return |= $match;
        }
        else {
          $return &= $match;
        }
      }
      return $return;
    }
    return TRUE;
  }

}
