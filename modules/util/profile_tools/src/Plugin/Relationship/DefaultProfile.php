<?php

namespace Drupal\profile_tools\Plugin\Relationship;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\ctools\Plugin\RelationshipBase;

/**
 * Class DefaultProfile
 *
 * @Relationship(
 *   id = "default_profile",
 *   deriver = "\Drupal\profile_tools\Plugin\Deriver\ProfileTypeDeriver"
 * )
 *
 * @package Drupal\profile_tools\Plugin\Relationship
 */
class DefaultProfile extends RelationshipBase {

  /**
   * Generates a context based on this plugin's configuration.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface
   */
  public function getRelationship() {
    $user = $this->getContextValue('user');
    $type = $this->getPluginDefinition()['profile_type'];

    $context_definition = new EntityContextDefinition('profile', $this->getPluginDefinition()['label']);
    $context_definition->addConstraint('Bundle', [$type]);

    if ($user) {
      /** @var \Drupal\profile\ProfileStorageInterface $storage */
      $storage = \Drupal::entityTypeManager()->getStorage('profile');
      if ($profile = $storage->loadDefaultByUser($user, $type)) {
        $context_definition->setDefaultValue($profile);
        return new Context($context_definition, $profile);
      }
    }

    return new Context($context_definition);
  }

  /**
   * The name of the property used to get this relationship.
   *
   * @return string
   */
  public function getName() {
    return 'profile_'.$this->getPluginDefinition()['profile_type'];
  }
}
