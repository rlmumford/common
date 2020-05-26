<?php

namespace Drupal\task_job\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

class JobListBuilder extends EntityListBuilder {

  public function buildHeader() {
    return [
      'title' => $this->t('Job'),
    ] + parent::buildHeader();
  }

  public function buildRow(EntityInterface $entity) {
    return [
      'title' => $entity->label(),
    ] + parent::buildRow($entity);
  }
}
