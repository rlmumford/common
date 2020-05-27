<?php

namespace Drupal\project\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Class to build lists of project entities.
 */
class ProjectListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'label' => $this->t('Project'),
      'type' => $this->t('Type'),
      'manager' => $this->t('Manager'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['title']['data'] = array(
      '#type' => 'link',
      '#title' => $entity->label(),
      '#url' => $entity->urlInfo(),
    );
    $row['type'] = $entity->type->entity->label();
    $row['manager']['data'] = $entity->manager->view();
    $row['operations']['data'] = $this->buildOperations($entity);
    return $row + parent::buildRow($entity);
  }

}
