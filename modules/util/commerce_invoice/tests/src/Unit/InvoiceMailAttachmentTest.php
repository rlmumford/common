<?php

namespace Drupal\Tests\commerce_invoice\Unit;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\mimemail\Utility\MimeMailFormatHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mime\MimeTypeGuesserInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\FileInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Ensures private invoice files are attached without broad file permissions.
 *
 * @group commerce_invoice
 */
class InvoiceMailAttachmentTest extends UnitTestCase {

  /**
   * Tests trusted invoice content and failure when the invoice is unreadable.
   *
   * @dataProvider invoiceCases
   */
  public function testInvoiceAttachment(bool $readable) {
    require_once __DIR__ . '/../../../commerce_invoice.module';
    $contents = "%PDF-1.4\nInvoice fixture\n%%EOF\n";
    $file = $this->createMock(FileInterface::class);
    $file->method('getFileUri')->willReturn($readable ? 'data://text/plain;base64,' . base64_encode($contents) : '/nonexistent-cjp1-19-invoice.pdf');
    $file->method('getFilename')->willReturn('invoice.pdf');
    $file->method('getMimeType')->willReturn('application/pdf');
    $field = $this->createMock(FieldItemList::class);
    $field->method('isEmpty')->willReturn(FALSE);
    $field->method('__get')->with('entity')->willReturn($file);
    $order = $this->createMock(OrderInterface::class);
    $order->method('get')->with('invoice_pdf')->willReturn($field);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($readable ? $this->never() : $this->once())->method('error');
    $loggers = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggers->method('get')->willReturn($logger);
    $container = new ContainerBuilder();
    $container->set('logger.factory', $loggers);
    \Drupal::setContainer($container);
    $message = ['id' => 'commerce_order_receipt', 'params' => ['order' => $order], 'send' => TRUE];
    commerce_invoice_mail_alter($message);
    if ($readable) {
      $this->assertSame([
        'filecontent' => $contents,
        'filename' => 'invoice.pdf',
        'filemime' => 'application/pdf',
      ], $message['params']['attachments'][0]);
      $this->assertTrue($message['send']);
    }
    else {
      $this->assertFalse($message['send']);
      $this->assertArrayNotHasKey('attachments', $message['params']);
    }
  }

  /**
   * Tests the formatter accepts binary content without treating it as a path.
   */
  public function testBinaryContentIsNotAFilePath() {
    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->expects($this->never())->method('realpath');
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([['default_scheme', 'public']]);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->willReturn($config);
    $user = $this->createMock(AccountProxyInterface::class);
    $user->method('hasPermission')->willReturn(FALSE);
    $requests = new RequestStack();
    $requests->push(Request::create('http://localhost/'));
    $container = new ContainerBuilder();
    $container->set('file_system', $file_system);
    $container->set('file.mime_type.guesser', $this->createMock(MimeTypeGuesserInterface::class));
    $container->set('config.factory', $factory);
    $container->set('current_user', $user);
    $container->set('request_stack', $requests);
    \Drupal::setContainer($container);
    $contents = "%PDF-1.4\nBinary\0content\n%%EOF\n";
    MimeMailFormatHelper::mimeMailFile(NULL, $contents, 'invoice.pdf', 'application/pdf', 'attachment');
    $parts = array_values(MimeMailFormatHelper::mimeMailFile());
    $this->assertCount(1, $parts);
    $this->assertSame($contents, $parts[0]['file']);
    $this->assertSame('attachment', $parts[0]['Content-Disposition']);
  }

  /**
   * Invoice readability cases.
   */
  public function invoiceCases() {
    return [[TRUE], [FALSE]];
  }

}
