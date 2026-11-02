<?php

namespace Drupal\Tests\document\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormState;
use Drupal\document\Plugin\Field\FieldWidget\DocumentSelector;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;

/**
 * Checks the widget's upload rules through Drupal's real file validator.
 *
 * @group document
 */
class DocumentSelectorValidationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file'];

  /**
   * Tests allowed extensions, byte limits and an unset size limit.
   */
  public function testUploadValidation(): void {
    $this->installConfig(['system']);
    $this->installEntitySchema('file');
    $this->installEntitySchema('user');

    $validators = $this->widgetValidators('2 MB');
    $validator = $this->container->get('file.validator');
    $file = File::create([
      'uri' => 'temporary://cv.pdf',
      'filename' => 'cv.pdf',
      'filesize' => 2 * 1024 * 1024,
    ]);
    $this->assertCount(0, $validator->validate($file, $validators));

    $file->setSize(2 * 1024 * 1024 + 1);
    $this->assertCount(1, $validator->validate($file, $validators));

    $file->setSize(100);
    $file->setFilename('payload.exe');
    $this->assertCount(1, $validator->validate($file, $validators));

    $file->setFilename('cv.pdf');
    $file->setSize(3 * 1024 * 1024);
    $this->assertCount(0, $validator->validate($file, $this->widgetValidators('')));
  }

  /**
   * Builds the real widget without querying for previously uploaded documents.
   */
  private function widgetValidators(string $size): array {
    $field = BaseFieldDefinition::create('entity_reference')
      ->setName('document')
      ->setLabel('Document');
    $widget = new class('document_selector', [], $field, [
      'upload_extensions' => 'pdf docx',
      'upload_max_size' => $size,
    ], []) extends DocumentSelector {

      /**
       * {@inheritdoc}
       */
      protected function existingDocuments(): array {
        return [];
      }

    };
    $form = [];
    $element = $widget->formElement(
      $this->createMock(FieldItemListInterface::class),
      0,
      [],
      $form,
      new FormState(),
    );
    return $element['upload']['#upload_validators'];
  }

}
