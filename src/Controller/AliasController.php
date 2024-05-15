<?php

namespace Drupal\wwm_utility\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\wwm_utility\Form\FindAliasForm;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;

/**
 * Provides the profile UI for users.
 */
class AliasController extends ControllerBase {

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Information about the current HTTP request.
   */
  public function find(Request $request) {
    // $form = \Drupal::formBuilder()->buildForm($form, $form_state);
    $build['form'] = \Drupal::formBuilder()->getForm(FindAliasForm::class);

    // Retrieve the parameter from the URL if it exists.
    if ($system_path = $request->query->get('system_path')) {
      $alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
      $aliases = $alias_storage->loadByProperties(['path' => $system_path]);
      $table = [
        '#theme' => 'table',
        '#header' => [
          ['data' => 'Alias', '#attributes' => ['class' => 'text-align-right']], /* Cell aligned right */
        ],
        '#rows' => [],
      ];
      foreach ($aliases as $alias) {
        /** @var \Drupal\path_alias\Entity\PathAlias $alias */
        $url = Url::fromUserInput($alias->getPath());
        $table['#rows'][] = [
          ['data' => [
            '#type' => 'link',
            '#title' => $alias->getAlias(),
            '#url' => $url->setOption('attributes', ['title' => $alias->getAlias()]),
          ]]
        ];
      }
      $build['table'] = $table; 
    }

    return $build;
  }

}
