<?php

namespace Drupal\pdf_tools;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Render\BareHtmlPageRendererInterface;
use Drupal\Core\Render\RendererInterface;

abstract class PDFGeneratorBase implements PDFGeneratorInterface {

  /**
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Render\BareHtmlPageRenderer
   */
  protected $bareHtmlPageRenderer;

  /**
   * PDFGeneratorBase constructor.
   *
   * @param \Drupal\Core\File\FileSystem $file_system
   */
  public function __construct(
    FileSystem $file_system,
    EntityTypeManagerInterface $entity_type_manager,
    BareHtmlPageRendererInterface $bare_html_page_renderer
  ) {
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;
    $this->bareHtmlPageRenderer = $bare_html_page_renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function entityToPDF(EntityInterface $entity, $display = [], array $options = []) {
    $build = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId())
      ->view($entity, $display);

    $response = $this->bareHtmlPageRenderer->renderBarePage($build, $entity->label(), 'pdf', ['#show_messages' => FALSE]);
    $content = $response->getContent();

    return $this->generateFromHTML($content, $options);
  }

  /**
   * Get the outfile from the options.
   *
   * @param array $options
   *   If the key '__destination' is set this is used, otherwise a temporary file
   *   is created.
   *
   * @return string
   *   The file uri of the output file.
   */
  protected function getOutFile(array $options = array()) {
    if (isset($options['__destination'])) {
      $out_file = $options['__destination'];

      $directory = $this->fileSystem->dirname($out_file);
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $out_file = $this->fileSystem->getDestinationFilename($out_file, FileSystemInterface::EXISTS_RENAME);
    }
    else {
      $out_file = $this->tempnamWithExtension('pdf', 'pdfgen_');
    }

    return $out_file;
  }

  /**
   * Generate a tempory file with a given extension.
   *
   * @param $ext
   * @param $prefix
   * @param string $directory
   *
   * @return bool|string
   */
  protected function tempnamWithExtension($ext, $prefix, $directory = 'temporary://') {
    do {
      $tmp_file = $this->fileSystem->tempnam($directory, $prefix);
    } while (!rename($tmp_file, $tmp_file.'.'.$ext));
    $tmp_file .= '.'.$ext;

    return $tmp_file;
  }
}
