<?php

declare(strict_types=1);

namespace Drupal\Tests\typed_data_plus\Kernel;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\KernelTests\KernelTestBase;
use Drupal\typed_data_plus\DataFetcherInterface;

/**
 * Existing Entity Template filters are consumable without service overrides.
 *
 * @group typed_data_plus
 */
class EntityTemplateCoexistenceTest extends KernelTestBase {

  protected static $modules = ['system', 'typed_data', 'typed_data_plus', 'entity_template', 'field', 'options', 'user', 'entity_test'];

  public function testSharedFilterRegistry(): void {
    $fetcher = $this->container->get('typed_data_plus.data_fetcher');
    $this->assertInstanceOf(DataFetcherInterface::class, $fetcher);
    $this->assertInstanceOf(\Drupal\typed_data\DataFetcherInterface::class, $this->container->get('typed_data.data_fetcher'));
    $data = $this->container->get('typed_data_manager')->create(DataDefinition::create('timestamp'), 1704067200);
    $this->assertSame('1704153600', $fetcher->fetchFilteredData($data, "|date_add('P1D')")->getValue());
    $this->assertSame(1704067200, $data->getValue());
  }

  public function testLegacyWrappedFieldFilter(): void {
    $definition = \Drupal\Core\Field\BaseFieldDefinition::create('list_string')
      ->setName('choice')
      ->setTargetEntityTypeId('entity_test')
      ->setSetting('allowed_values', ['a' => 'First', 'b' => 'Second']);
    $entity = \Drupal\entity_test\Entity\EntityTest::create(['name' => 'Example']);
    $data = $this->container->get('typed_data_manager')->create(
      $definition, [['value' => 'b']], 'choice', $entity->getTypedData()
    );
    $fetcher = $this->container->get('typed_data_plus.data_fetcher');
    $this->assertSame('Second', $fetcher->fetchFilteredData($data, '|option_label')->getValue());
  }

}
