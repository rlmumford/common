<?php

namespace Drupal\flexilayout_builder\Plugin\Relationship;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\ctools\Plugin\RelationshipInterface;

/**
 * A ctools relationship with its own configuration and forms.
 *
 * ConfigurableInterface and DependentPluginInterface together replace
 * ConfigurablePluginInterface, which Drupal 10 removed.
 */
interface ConfigurableRelationshipInterface extends ConfigurableInterface, DependentPluginInterface, RelationshipInterface, PluginWithFormsInterface, PluginFormInterface {

}
