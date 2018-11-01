<?php

namespace Drupal\flexilayout_builder\Entity;

use Drupal\Component\Plugin\Exception\MissingValueContextException;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Section;

class FlexibleLayoutBuilderEntityViewDisplay extends LayoutBuilderEntityViewDisplay {

  /**
   * {@inheritdoc}
   */
  public function buildMultiple(array $entities) {
    $build_list = EntityViewDisplay::buildMultiple($entities);
    if (!$this->isLayoutBuilderEnabled()) {
      return $build_list;
    }

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    foreach ($entities as $id => $entity) {
      $sections = $this->getRuntimeSections($entity);
      if ($sections) {
        foreach ($build_list[$id] as $name => $build_part) {
          $field_definition = $this->getFieldDefinition($name);
          if ($field_definition && $field_definition->isDisplayConfigurable($this->displayContext)) {
            unset($build_list[$id][$name]);
          }
        }

        $contexts = $this->prepareContexts($entity);
        foreach ($sections as $delta => $section) {
          $build_list[$id]['_layout_builder'][$delta] = $this->sectionToRenderArray($section, $contexts);
        }
      }
    }

    return $build_list;
  }

  /**
   * Render a section. This is effectively an override of Section::toRenderArray()
   * that doesn't attempt to render a block if there are missing contexts.
   *
   * @param \Drupal\layout_builder\Section $section
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   * @param bool $in_preview
   *
   * @return array
   *
   * @see Section::toRenderArray()
   */
  protected function sectionToRenderArray(Section $section, array $contexts = [], $in_preview = FALSE) {
    $regions = [];
    foreach ($section->getComponents() as $component) {
      try {
        if ($output = $component->toRenderArray($contexts, $in_preview)) {
          $regions[$component->getRegion()][$component->getUuid()] = $output;
        }
      }
      catch (MissingValueContextException $missingValueContextException) {}
    }

    return $section->getLayout()->build($regions);
  }

  /**
   * Prepare contexts for layout rendering.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   Array of contexts keyed name.
   */
  protected function prepareContexts(FieldableEntityInterface $entity) {
    // Bypass ::getContexts() in order to use the runtime entity, not a
    // sample entity.
    $contexts = $this->contextRepository()->getAvailableContexts();
    $label = new TranslatableMarkup('@entity being viewed', [
      '@entity' => $entity->getEntityType()->getSingularLabel(),
    ]);
    $contexts['layout_builder.entity'] = EntityContext::fromEntity($entity, $label);

    $contexts += \Drupal::service('ctools.context_mapper')->getContextValues($this->getThirdPartySetting('flexilayout_builder', 'static_context', []));

    /** @var \Drupal\ctools\Plugin\RelationshipManager $relationship_manager */
    $relationship_manager = \Drupal::service('plugin.manager.ctools.relationship');
    /** @var \Drupal\Core\Plugin\Context\ContextHandler $context_handler */
    $context_handler = \Drupal::service('context.handler');

    foreach ($this->getThirdPartySetting('flexilayout_builder', 'relationships', []) as $machine_name => $relationship) {
      /** @var \Drupal\ctools\Plugin\RelationshipInterface $plugin */
      $plugin = $relationship_manager->createInstance($relationship['plugin'], $relationship['settings'] ?: []);
      $context_handler->applyContextMapping($plugin, $contexts);

      $contexts[$machine_name] = $plugin->getRelationship();
    }

    return $contexts;
  }

}
