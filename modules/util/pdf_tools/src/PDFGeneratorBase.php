<?php

namespace Drupal\pdf_tools;

use Drupal\Core\File\FileSystem;

abstract class PDFGeneratorBase implements PDFGeneratorInterface {

  /**
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * PDFGeneratorBase constructor.
   *
   * @param \Drupal\Core\File\FileSystem $file_system
   */
  public function __construct(FileSystem $file_system) {
    $this->fileSystem = $file_system;
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
    }
    else {
      $out_file = $this->tempnamWithExtension('.pdf', 'pdfgen_');
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
