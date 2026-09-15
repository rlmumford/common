<?php

namespace Drupal\commerce_invoice;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\ProxyClass\File\MimeType\ExtensionMimeTypeGuesser;
use Drupal\pdf_tools\PDFGeneratorInterface;
use Symfony\Component\Mime\MimeTypesInterface;

/**
 *
 */
class OrderInvoiceGenerator {

  /**
   * The pdf generator.
   *
   * @var \Drupal\pdf_tools\PDFGeneratorInterface
   */
  protected PDFGeneratorInterface $pdfGenerator;

  /**
   * The file entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $fileStorage;

  /**
   * The file system
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The file mimetype guesser.
   *
   * @var \Drupal\Core\ProxyClass\File\MimeType\ExtensionMimeTypeGuesser
   */
  protected ExtensionMimeTypeGuesser $mimeTypeGuesser;

  /**
   * @param \Drupal\pdf_tools\PDFGeneratorInterface $pdf_generator
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   * @param \Drupal\Core\ProxyClass\File\MimeType\ExtensionMimeTypeGuesser $mime_type_guesser
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    PDFGeneratorInterface $pdf_generator,
    EntityTypeManagerInterface $entityTypeManager,
    FileSystemInterface $file_system,
    ExtensionMimeTypeGuesser $mime_type_guesser
  ) {
    $this->pdfGenerator = $pdf_generator;
    $this->fileStorage = $entityTypeManager->getStorage('file');
    $this->fileSystem = $file_system;
    $this->mimeTypeGuesser = $mime_type_guesser;
  }

  /**
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\pdf_tools\PDFGenerationException
   */
  public function generatePDF($order, $should_save = TRUE): \Drupal\file\Entity\File {

    $options = [
      '__destination' => 'private://invoices/invoice_'.$order->getOrderNumber().'.pdf',
      'page-size' => 'A4',
    ];
    \Drupal::moduleHandler()->alter('commerce_invoice_pdf_options', $options, $order);
    $uri = $this->pdfGenerator->entityToPDF($order, 'invoice', $options);

    /** @var \Drupal\file\Entity\File $file */
    $file = $this->fileStorage->create([
      'uri' => $uri,
      'size' => filesize($uri),
      'uid' => \Drupal::currentUser()->id(),
      'status' => 1,
      'filename' => basename($uri),
      'filemime' => $this->mimeTypeGuesser->guessMimeType($uri),
    ]);

    $this->fileSystem->chmod($file->getFileUri());
    $file->save();

    $order->invoice_pdf = $file;
    if ($should_save) {
      $order->save();
    }
    return $file;
  }
}
