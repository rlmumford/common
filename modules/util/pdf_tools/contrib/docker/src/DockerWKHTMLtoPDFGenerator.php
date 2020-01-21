<?php

namespace Drupal\pdf_tools_docker;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Render\BareHtmlPageRendererInterface;
use Drupal\pdf_tools\PDFGenerationException;
use Drupal\pdf_tools\WKHTMLtoPDFGenerator;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use function GuzzleHttp\Psr7\build_query;

class DockerWKHTMLtoPDFGenerator extends WKHTMLtoPDFGenerator {

  /**
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * DockerWKHTMLtoPDFGenerator constructor.
   *
   * @param \Drupal\Core\File\FileSystem $file_system
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Render\BareHtmlPageRendererInterface $bare_html_page_renderer
   * @param \GuzzleHttp\ClientInterface $http_client
   */
  public function __construct(
    FileSystem $file_system,
    EntityTypeManagerInterface $entity_type_manager,
    BareHtmlPageRendererInterface $bare_html_page_renderer,
    ConfigFactoryInterface $config_factory,
    ClientInterface $http_client
  ) {
    parent::__construct($file_system, $entity_type_manager, $bare_html_page_renderer);

    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
  }

  public function generateFromURL($url, array $options = array()) {
    $out_file = $this->getOutFile($options);
    $config = $this->configFactory->get('pdf_tools.docker.containers');

    try {
      $post_body = [];
      $post_body['url'] = str_replace(\Drupal::request()->getBaseUrl(), 'http://'.($config->get('web') ?: 'nginx').':80', $url);
      $post_body['options'] = $this->prepareOptions($options);

      $response = $this->httpClient->request(
        'post',
        'http://'.($config->get('wkhtmltopdf') ?: 'wkhtmltopdf').':80/pdf',
        [
          'body' => build_query($post_body),
        ]
      );

      $h = fopen($out_file, 'w');
      fwrite($h, $response->getBody()->getContents());
      fclose($h);

      return $out_file;
    }
    catch (GuzzleException $exception) {
      throw new PDFGenerationException($exception->getMessage(), 0, $exception);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function generate($in_file, array $options = array()) {
    $out_file = $this->getOutFile($options);
    $config = $this->configFactory->get('pdf_tools.docker.containers');

    try {
      $post_body = [];
      $post_body['html'] = preg_replace(
        '/(src="|href=")'.\Drupal::request()->getBaseUrl().'/i',
        '${1}http://'.($config->get('web') ?: 'nginx').':80',
        file_get_contents($in_file)
      );
      $post_body['options'] = $this->prepareOptions($options);

      $response = $this->httpClient->request(
        'post',
        'http://'.($config->get('wkhtmltopdf') ?: 'wkhtmltopdf').':80/pdf',
        [
          'form_params' => $post_body,
        ]
      );

      $h = fopen($out_file, 'w');
      fwrite($h, $response->getBody()->getContents());
      fclose($h);

      return $out_file;
    }
    catch (GuzzleException $exception) {
      throw new PDFGenerationException($exception->getMessage(), 0, $exception);
    }
  }

  /**
   * @param array $options
   *
   * @return array|mixed
   */
  protected function prepareOptions(array $options = array()) {
    $options = parent::prepareOptions($options);
    $prepared_options = [];

    foreach ($options as $key => $option) {
      if (!is_numeric($key)) {
        $prepared_options[$key] = $option;
        continue;
      }

      list($key, $value) = explode(' ', $option, 2);
      $key = ltrim($key, '-');

      // Convert known short arguments to long arguments
      switch ($key) {
        case 'T':
          $key = 'margin-top';
          break;
        case 'R':
          $key = 'margin-right';
          break;
        case 'B':
          $key = 'margin-bottom';
          break;
        case 'L':
          $key = 'margin-left';
          break;
        case 's':
          $key = 'page-size';
          break;
        default:
          break;
      }
      $prepared_options[$key] = isset($value) ? $value : '';
    }

    foreach (['header-html', 'footer-html'] as $html) {
      if (isset($prepared_options[$html])) {
        $prepared_options[$html] = str_replace(
          \Drupal::request()->getBaseUrl(),
          'http://' . ($this->configFactory->get('pdf_tools.docker.containers')->get('web') ?: 'nginx') . ':80',
          $prepared_options[$html]
        );

        if (substr($prepared_options[$html], 0, 1) === '/') {
          $prepared_options[$html] = 'http://' . ($this->configFactory->get('pdf_tools.docker.containers')->get('web') ?: 'nginx') . ':80'.$prepared_options[$html];
        }
      }
    }

    dpm($prepared_options, 'After');

    return $prepared_options;
  }

}
