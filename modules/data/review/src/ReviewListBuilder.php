<?php

namespace Drupal\review;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Class to build lists of review entities.
 */
class ReviewListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'label' => $this->t('Review'),
      'type' => $this->t('Type'),
      'manager' => $this->t('Manager'),
      'recipients' => $this->t('Recipients'),
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
    $row['manager']['recipients'] = $entity->recipients->view();
    $row['operations']['data'] = $this->buildOperations($entity);
    return $row + parent::buildRow($entity);
  }

}
