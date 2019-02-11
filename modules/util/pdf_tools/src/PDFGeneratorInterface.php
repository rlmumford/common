<?php

namespace Drupal\pdf_tools;

interface PDFGeneratorInterface {

   public function generateFromFile($uri, array $options = array());

   public function generateFromURL($url, array $options = array());

   public function generateFromHTML($content, array $options = array());

}
