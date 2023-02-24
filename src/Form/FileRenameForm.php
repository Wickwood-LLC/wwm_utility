<?php

namespace Drupal\wwm_utility\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\File\FileSystemInterface;


class FileRenameForm extends FormBase {

  /**
   * The file entity being used by this form.
   *
   * @var \Drupal\file\FileInterface
   */
  protected $file;

  /**
   * FileSystem service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a new FileRenameForm.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system handler.
   */
  public function __construct(FileSystemInterface $file_system) {
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wwm_utility_file_rename_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $file = NULL) {
    $this->file = $file;
    $form['fid'] = [
      '#type' => 'markup',
      '#markup' => $this->t('File ID: @fid', ['@fid' => $this->file->id()]),
    ];

    $form['path'] = [
      '#type' => 'markup',
      '#markup' => $this->t('File path: @uri', ['@uri' => $this->file->getFileUri()]),
    ];

    $form['mime'] = [
      '#type' => 'markup',
      '#markup' => $this->t('File mime: @mime', ['@mime' => $this->file->getMimeType()]),
    ];
    $form['new_filename'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filename'),
      '#default_value' => $file->getFilename(),
    ];

    if ($this->file->hasField('origname') && !empty($this->file->get('origname')->value)) {
      $form['origname'] = [
        '#type' => 'markup',
        '#markup' => $this->t('File origname: @origname', ['@origname' => $this->file->get('origname')->value]),
      ];

      $form['change_origname'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Change origname property too'),
        '#default_value' => TRUE,
      ];
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $file = $this->file;
    $filename_new = $form_state->getValue('new_filename');
    if ($filename_new != $file->getFilename()) {
      // $filepath_new = $this->getRenamedFilePath($form_state);
      $filepath_new = static::strLReplace($this->file->getFilename(), $filename_new, $this->file->getFileUri());
      // Rename existing file.
      $this->fileSystem->move($this->file->getFileUri(), $filepath_new, FileSystemInterface::EXISTS_REPLACE);
      $log_args = [
        '%old' => $this->file->getFilename(),
        '%new' => $filename_new,
      ];
      // Update file entity.
      $this->file->setFilename($filename_new);
      $this->file->setFileUri($filepath_new);
      if (!empty($form_state->getValue('change_origname'))) {
        $this->file->set('origname', $filename_new);
      }
      $status = $this->file->save();
      // Notify and log.
      $this->messenger()->addStatus($this->t('File %old was renamed to %new', $log_args));
      $this->logger('file_entity')->info('File %old renamed to %new', $log_args);

      return $status;
    }
  }

  /**
   * Get Renamed File Path.
   */
  protected function getRenamedFilePath(FormStateInterface $form_state) {
    $pathinfo = pathinfo($this->file->getFileUri());
    $old_filename = $pathinfo['filename'];
    $new_filename = $form_state->getValue('new_filename');
    // Path after renaming.
    return str_replace($old_filename, $new_filename, $this->file->getFileUri());
  }

  /**
   * From https://stackoverflow.com/a/3835653
   */
  public static function strLReplace($search, $replace, $subject) {
    $pos = strrpos($subject, $search);

    if($pos !== FALSE) {
        $subject = substr_replace($subject, $replace, $pos, strlen($search));
    }
    return $subject;
  }
}