<?php

namespace Drupal\document_task_links\Plugin\views\argument;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Restricts tasks through a subquery, preserving distinct rows and counts.
 *
 * @ViewsArgument("document_task_links")
 */
final class RelatedDocument extends ArgumentPluginBase {

  /**
   * Constructs the contextual filter.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, private readonly Connection $database, private readonly EntityTypeManagerInterface $entities, private readonly ModuleHandlerInterface $modules) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('database'), $container->get('entity_type.manager'), $container->get('module_handler'));
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();
    $document = ctype_digit((string) $this->argument) ? $this->entities->getStorage('document')->load($this->argument) : NULL;
    if (!$document || !$document->access('view')) {
      $this->query->addWhereExpression(0, '1 = 0');
      return;
    }
    $query = $this->database->select('document_task_link', 'l')->fields('l', ['task_id'])->condition('l.document_id', $document->id());
    $query->innerJoin('task', 'related_task', 'related_task.id = l.task_id');
    // Consumers can constrain the relationship itself, including historical
    // data predating write validation. Task query access remains enabled too.
    $this->modules->alter('document_task_links_query', $query, $document);
    $this->query->addWhere(0, $this->tableAlias . '.id', $query, 'IN');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
