<?php

namespace Drupal\note\Form;

use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Form to reply to a note.
 */
class NoteReplyForm extends NoteForm {

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id) {
    /** @var \Drupal\note\Entity\Note $note */
    $note = parent::getEntityFromRouteMatch($route_match, $entity_type_id);
    return $note->createReply();
  }

}
