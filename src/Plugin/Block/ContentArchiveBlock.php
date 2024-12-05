<?php

namespace Drupal\wwm_utility\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateHelper;
use Drupal\Core\Url;
use Drupal\Core\Render\RendererInterface;

/**
 * Provides a block to list arhive links for contens over year/months.
 *
 * @Block(
 *   id = "wwm_content_archive",
 *   admin_label = @Translation("Content Archive Listing"),
 * )
 */
class ContentArchiveBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Active database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;


  /**
   * Creates a LocalTasksBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection to be used.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database, RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'content_types' => '',
      'link_title_template' => '{{ month_name }} {{ year }}',
      'link_url_template' => '/content/{{ year }}/{{ month_number }}',
      'item_template' => '{{ link }} ({{ count }})',
      'group_by' => 'month',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $types = array_map(['\Drupal\Component\Utility\Html', 'escape'], node_type_get_names());

    $form['content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content Type'),
      '#description' => $this->t('If not selected one then the listing will be for all content types'),
      '#default_value' => $this->configuration['content_types'],
      '#options' => $types,
    ];

    $link_component_help = $this->t('Available variables are {{ year }}, {{ month_name }}, {{ month_number }} and {{ count }}.');

    $form['link_title_template'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link Title Template'),
      '#description' => $link_component_help,
      '#default_value' => $this->configuration['link_title_template'],
    ];

    $form['link_url_template'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link URL Template'),
      '#description' => $link_component_help,
      '#default_value' => $this->configuration['link_url_template'],
    ];

    $form['item_template'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Item Template'),
      '#description' => $this->t('Available variables are {{ link }}, {{ year }}, {{ month_name }}, {{ month_number }} and {{ count }}.'),
      '#default_value' => $this->configuration['item_template'],
    ];

    $form['group_by'] = [
      '#type' => 'radios',
      '#title' => $this->t('Group By'),
      '#options' => [
        'year' => $this->t('Year'),
        'month' => $this->t('Month'),
      ],
      '#default_value' => $this->configuration['group_by'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['content_types'] = $form_state->getValue('content_types');
    $this->configuration['link_title_template'] = $form_state->getValue('link_title_template');
    $this->configuration['link_url_template'] = $form_state->getValue('link_url_template');
    $this->configuration['item_template'] = $form_state->getValue('item_template');
    $this->configuration['group_by'] = $form_state->getValue('group_by');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    if ($this->configuration['group_by'] == 'year') {
      $frequency_format = '%Y';
    }
    else {
      $frequency_format = '%Y-%m';
    }

    $query = $this->database->select('node_field_data', 'nfd');
    $query->addExpression("FROM_UNIXTIME(published_at,'" . $frequency_format . "')", 'value');
    $query->addExpression('COUNT(published_at)', 'count');

    if (!empty(array_filter($this->configuration['content_types']))) {
      $query->condition('type', array_filter($this->configuration['content_types']), 'IN');
    }
    $result = $query->condition('status', TRUE)
        ->groupBy('value')
        ->orderBy('value', 'DESC')
        ->execute()->fetchAll();

    $items = [];

    $date_helper = new DateHelper();

    foreach ($result as $item) {
      $matches = NULL;
      preg_match('/(\d+)(\-(\d+))?/', $item->value, $matches);
      $year = $matches[1];
      if (!empty($matches[3])) {
        $month_number = $matches[3];
        $month_name = $date_helper->monthNames()[(int)$matches[3]];
      }
      else {
        $month_number = NULL;
        $month_name = NULL;
      }

      $render_vars = [
        'year' => $year,
        'month_number' => $month_number,
        'month_name' => $month_name,
        'count' => $item->count,
      ];

      $title_render_array = [
        '#type' => 'inline_template',
        '#template' => $this->configuration['link_title_template'],
        '#context' => $render_vars,
      ];

      $link_url_render_array = [
        '#type' => 'inline_template',
        '#template' => $this->configuration['link_url_template'],
        '#context' => $render_vars,
      ];

      $link_render_array = [
        '#type' => 'link',
        '#title' => $this->renderer->renderInIsolation($title_render_array),
        '#url' => Url::fromUserInput($this->renderer->renderInIsolation($link_url_render_array))
      ];

      $render_vars['link'] = $this->renderer->renderInIsolation($link_render_array);
  
      $item_render_array = [
        '#type' => 'inline_template',
        '#template' => $this->configuration['item_template'],
        '#context' => $render_vars,
      ];
      $items[] = $this->renderer->renderInIsolation($item_render_array);
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
  }


  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return ['node_view'];
  }
}
