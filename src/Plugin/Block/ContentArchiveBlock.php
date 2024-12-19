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

  const GROUP_BY_YEAR = 'year';
  const GROUP_BY_MONTH = 'month';
  const GROUP_BY_YEAR_MONTH = 'year_month';

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
      'second_level' => [
        'link_title_template' => '{{ month_name }} {{ year }}',
        'link_url_template' => '/content/{{ year }}/{{ month_number }}',
        'item_template' => '{{ link }} ({{ count }})',
      ],
      'group_by' => static::GROUP_BY_MONTH,
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
        static::GROUP_BY_YEAR => $this->t('Year'),
        static::GROUP_BY_MONTH => $this->t('Month'),
        static::GROUP_BY_YEAR_MONTH => $this->t('Year/Month'),
      ],
      '#default_value' => $this->configuration['group_by'],
    ];

    $form['second_level'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Second Level'),
      '#description' => $this->t('Options for second level items.'),
      '#states' => [
        'visible' => [
          'input[name="settings[group_by]"]' => ['value' => static::GROUP_BY_YEAR_MONTH],
        ],
      ],
    ];

    $form['second_level']['link_title_template'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link Title Template'),
      '#description' => $link_component_help,
      '#default_value' => $this->configuration['second_level']['link_title_template'] ?? '',
    ];

    $form['second_level']['link_url_template'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link URL Template'),
      '#description' => $link_component_help,
      '#default_value' => $this->configuration['second_level']['link_url_template'] ?? '',
    ];

    $form['second_level']['item_template'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Item Template'),
      '#description' => $this->t('Available variables are {{ link }}, {{ year }}, {{ month_name }}, {{ month_number }} and {{ count }}.'),
      '#default_value' => $this->configuration['second_level']['item_template'] ?? '',
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
    $this->configuration['second_level'] = $form_state->getValue('second_level');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    if ($this->configuration['group_by'] == static::GROUP_BY_YEAR) {
      $frequency_format = '%Y';
    }
    else {
      // This handles both month and year_month options.
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

    $list = [];

    $date_helper = new DateHelper();

    $data = [];
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
      $data[$year] = $data[$year] ?? [];
      if ($this->configuration['group_by'] == static::GROUP_BY_YEAR) {
        $data[$year]['count'] = $item->count;
      }
      else {
        $data[$year]['months_data'] = $data[$year]['months_data'] ?? [];
        $data[$year]['months_data'][$month_number] = [
          'name' => $month_name,
          'count' => $item->count,
        ];
      }
    }
    foreach ($data as $year => $year_data) {
      if (!isset($year_data['count']) && isset($year_data['months_data'])) {
        $count = 0;
        foreach ($year_data['months_data'] as $month_data) {
          $count += $month_data['count'];
        }
        $data[$year]['count'] = $count;
      }
    }

    foreach ($data as $year => $year_data) {
      $year_item = [];

      $render_vars = [
        'year' => $year,
        'count' => $year_data['count'],
      ];

      if ($this->configuration['group_by'] == static::GROUP_BY_YEAR || $this->configuration['group_by'] == static::GROUP_BY_YEAR_MONTH) {
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

        $year_item = [
          '#type' => 'inline_template',
          '#template' => $this->configuration['item_template'],
          '#context' => $render_vars,
        ];
      }
  
      if (isset($year_data['months_data'])) {
        if ($this->configuration['group_by'] == static::GROUP_BY_YEAR_MONTH) {
          $year_item['months'] = [
            '#theme' => 'item_list',
            '#items' => [],
          ];
          foreach ($year_data['months_data'] as $month_number => $month_data) {
            $render_vars['month_number'] = $month_number;
            $render_vars['month_name'] = $month_data['name'];
            $render_vars['count'] = $month_data['count'];

            $title_render_array = [
              '#type' => 'inline_template',
              '#template' => $this->configuration['second_level']['link_title_template'],
              '#context' => $render_vars,
            ];
      
            $link_url_render_array = [
              '#type' => 'inline_template',
              '#template' => $this->configuration['second_level']['link_url_template'],
              '#context' => $render_vars,
            ];
      
            $link_render_array = [
              '#type' => 'link',
              '#title' => $this->renderer->renderInIsolation($title_render_array),
              '#url' => Url::fromUserInput($this->renderer->renderInIsolation($link_url_render_array))
            ];
      
            $render_vars['link'] = $this->renderer->renderInIsolation($link_render_array);
            $year_item['months']['#items'][$month_number] = [
              '#type' => 'inline_template',
              '#template' => $this->configuration['second_level']['item_template'],
              '#context' => $render_vars,
            ];
          }
        }
        else if ($this->configuration['group_by'] == static::GROUP_BY_MONTH) {

          foreach ($year_data['months_data'] as $month_number => $month_data) {
            $render_vars['month_number'] = $month_number;
            $render_vars['month_name'] = $month_data['name'];
            $render_vars['count'] = $month_data['count'];

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
    
            $year_item = [
              '#type' => 'inline_template',
              '#template' => $this->configuration['item_template'],
              '#context' => $render_vars,
            ];
            $list[$year . '-' . $month_number] = $year_item;
          }
        }
      }

      if ($this->configuration['group_by'] == static::GROUP_BY_YEAR|| $this->configuration['group_by'] == static::GROUP_BY_YEAR_MONTH) {
        $list[$year] = $year_item;
      }
    }

    return [
      '#theme' => 'item_list',
      '#items' => $list,
    ];
  }


  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return ['node_view'];
  }
}
