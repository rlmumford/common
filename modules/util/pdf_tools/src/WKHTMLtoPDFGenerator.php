<?php

namespace Drupal\pdf_tools;

class WKHTMLtoPDFGenerator extends PDFGeneratorBase {

  /**
   * Supported WKHTMLtoPDF Options.
   */
  protected $supportedOptions = [
    'T','R','B','L','s', 'margin-top', 'margin-bottom', 'margin-left',
    'margin-right', 'page-size', 'footer-html', 'header-html'
  ];

  /**
   * {@inheritdoc}
   */
  public function generateFromFile($uri, array $options = array()) {
    return $this->generate(
      $this->fileSystem->realpath($uri),
      $options
    );
  }

  /**
   * {@inheritdoc}
   */
  public function generateFromURL($url, array $options = array()) {
    return $this->generate($url, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function generateFromHTML($content, array $options = array()) {
    $in_file = $this->tempnamWithExtension('html', 'pdfhtml_');
    file_put_contents($in_file, $content);
    $return = $this->generateFromFile($in_file, $options);
    unlink($in_file);
    return $return;
  }

  /**
   * Generate a pdf
   *
   * @param $in_file
   * @param array $options
   *
   * @return null|string
   *
   * @throws \Drupal\pdf_tools\PDFGenerationException
   */
  protected function generate($in_file, array $options = array()) {
    $out_file = $this->getOutFile($options);
    $out_real_file = $this->fileSystem->realpath($out_file);

    $script = "wkhtmltopdf ".implode(' ', $this->prepareOptions($options))." \"{$in_file}\" \"{$out_real_file}\"";
    exec($script);

    if (file_exists($out_real_file) && (filesize($out_real_file) > 0)) {
      return $out_file;
    }
    else {
      return NULL;
    }
  }

  /**
   * Prepare the options.
   *
   * @param array $options
   *
   * @return array
   */
  protected function prepareOptions(array $options = array()) {
    $prepped_options = [];

    foreach ($options as $key => $value) {
      if (!in_array($key, $this->supportedOptions)) {
        continue;
      }

      if (strlen($key) > 1) {
        $prefix = '--';
      }
      else {
        $prefix = '-';
      }

      $prepped_options[] = $prefix.$key.' '.$value;
    }

    return $prepped_options;
  }
}
