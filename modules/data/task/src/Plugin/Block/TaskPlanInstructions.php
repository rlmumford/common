<?php

namespace Drupal\task\Plugin\Block;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Class TaskPlanInstructions
 *
 * @Block(
 *   id = "task_plan_instructions",
 *   admin_label = @Translation("Task Plan Instructions"),
 *   context = {
 *     "job" = @ContextDefinition("entity:task", label = @Translation("Task"))
 *   }
 * )
 */
class TaskPlanInstructions extends BlockBase {

  /**
   * Builds and returns the renderable array for this block plugin.
   *
   * If a block should not be rendered because it has no content, then this
   * method must also ensure to return no content: it must then only return an
   * empty array, or an empty array with #cache set (with cacheability metadata
   * indicating the circumstances for it being empty).
   *
   * @return array
   *   A renderable array representing the content of the block.
   *
   * @see \Drupal\block\BlockViewBuilder
   */
  public function build() {
    try {
      /** @var \Drupal\task\Entity\Task $task */
      $task = $this->getContextValue('task');
      /** @var \Drupal\task\Entity\TaskPlan $plan */
      $plan = $task->plan->entity;

      $cacheable_metadata = new BubbleableMetadata();
      $cacheable_metadata->addCacheableDependency($task);
      $cacheable_metadata->addCacheableDependency($plan);

      $build = [];

      if ($instructions = $plan->get('instructions')) {
        $content = \Drupal::token()->replace($instructions['value'], [
          'task' => $task,
          'task_plan' => $plan,
        ], $cacheable_metadata);

        $build = [
          '#type' => 'processed_text',
          '#text' => $content,
          '#format' => $instructions['format'],
        ];
      }

      $build['#cache'] = [
        'contexts' => $cacheable_metadata->getCacheContexts(),
        'tags' => $cacheable_metadata->getCacheTags(),
        'max-age' => $cacheable_metadata->getCacheMaxAge(),
      ];

      return $build;
    }
    catch (PluginException $exception) {
      return [
        '#cache' => [ 'max-age' => 0 ],
      ];
    }
  }
}
