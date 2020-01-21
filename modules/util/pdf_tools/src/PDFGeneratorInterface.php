<?php

namespace Drupal\pdf_tools;

use Drupal\Core\Entity\EntityInterface;

interface PDFGeneratorInterface {

  /**
   * Generate a pdf from an HTML file.
   *
   * @param $uri
   * @param array $options
   *
   * @return string
   *
   * @throws \Drupal\pdf_tools\PDFGenerationException
   */
   public function generateFromFile($uri, array $options = []);

  /**
   * Generate from a web url.
   *
   * @param $url
   * @param array $options
   *
   * @return string
   *
   * @throws \Drupal\pdf_tools\PDFGenerationException
   */
   public function generateFromURL($url, array $options = []);

  /**
   * Generate from html content.
   *
   * @param $content
   * @param array $options
   *
   * @return string
   *
   * @throws \Drupal\pdf_tools\PDFGenerationException
   */
   public function generateFromHTML($content, array $options = []);

  /**
   * Print an entity as a PDF.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param array $display
   * @param array $options
   *
   * @return string
   *
   * @throws \Drupal\pdf_tools\PDFGenerationException
   */
   public function entityToPDF(EntityInterface $entity, $display = [], array $options = []);

}
