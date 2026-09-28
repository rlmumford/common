<?php

namespace Drupal\Tests\checklist\Unit;

use Drupal\checklist\ChecklistActionResource;
use Drupal\checklist\ChecklistActionResourcePaneBuilder;
use Drupal\checklist\ChecklistInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests checklist resource pane metadata for responsive consumers.
 *
 * @group checklist
 *
 * @coversDefaultClass \Drupal\checklist\ChecklistActionResourcePaneBuilder
 */
class ChecklistActionResourcePaneBuilderTest extends UnitTestCase {

  /**
   * The builder under test.
   *
   * @var \Drupal\checklist\ChecklistActionResourcePaneBuilder
   */
  protected ChecklistActionResourcePaneBuilder $builder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->builder = new ChecklistActionResourcePaneBuilder();
  }

  /**
   * The resource pane exposes labels, owners and responsive panel identity.
   *
   * @covers ::build
   */
  public function testResourcePanelMetadata(): void {
    $entity = $this->createMock(FieldableEntityInterface::class);
    $entity->method('uuid')->willReturn('host-uuid');
    $checklist = $this->createMock(ChecklistInterface::class);
    $checklist->method('getEntity')->willReturn($entity);
    $checklist->method('getKey')->willReturn('work');
    $resource = new ChecklistActionResource(
      'employment',
      ['#markup' => 'Employment details'],
      'Employment',
      10,
      TRUE,
      'briefcase',
      TRUE
    );

    $pane = $this->builder->build([
      'employment' => [
        'resource' => $resource,
        'owners' => ['employment_review'],
      ],
    ], $checklist);

    $this->assertSame('resources', $pane['#attributes']['data-checklist-workspace-panel']);
    $this->assertSame('Employment', $pane['panels']['employment']['#attributes']['data-resource-label']);
    $this->assertSame('employment_review', $pane['panels']['employment']['#attributes']['data-resource-owners']);
    $this->assertSame('true', $pane['panels']['employment']['#attributes']['data-resource-pinned']);
  }

}
